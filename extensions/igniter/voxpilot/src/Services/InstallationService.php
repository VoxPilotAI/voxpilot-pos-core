<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Models\AuthCode;
use Igniter\VoxPilot\Models\Installation;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallationService
{
    public const CODE_TTL_SECONDS = 600;

    public const INSTALL_TOKEN_NAME = 'VoxPilot Install';

    /**
     * @return array{authorization_url: string, expires_at: string, code: string, state: string}
     */
    public function createAuthorization(int $tenantId, ?int $createdByUserId = null, ?string $redirectUri = null): array
    {
        $installation = Installation::where('tenant_id', $tenantId)->first();
        if ($installation?->isConnected()) {
            throw new InstallationException('already_connected', 'This tenant is already connected to VoxPilot.');
        }

        $tenant = Tenant::findOrFail($tenantId);
        $code = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $state = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        $expiresAt = now()->addSeconds(self::CODE_TTL_SECONDS);
        $redirect = $redirectUri ?: (string) config('voxpilot.install_redirect_uri');

        AuthCode::create([
            'tenant_id' => $tenant->id,
            'code_hash' => hash('sha256', $code),
            'state' => $state,
            'redirect_uri' => $redirect,
            'expires_at' => $expiresAt,
            'created_by_user_id' => $createdByUserId,
        ]);

        Installation::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'external_tenant_id' => (string) $tenant->external_tenant_id,
                'status' => Installation::STATUS_INSTALLATION_PENDING,
                'assistant_id' => null,
                'voxpilot_connection_id' => null,
                'connected_at' => null,
                'disconnected_at' => null,
            ]
        );

        $separator = str_contains($redirect, '?') ? '&' : '?';
        $authorizationUrl = $redirect.$separator.http_build_query([
            'code' => $code,
            'state' => $state,
        ]);

        return [
            'authorization_url' => $authorizationUrl,
            'expires_at' => $expiresAt->toIso8601String(),
            'code' => $code,
            'state' => $state,
        ];
    }

    /**
     * @return array{pos_tenant_id: int, external_tenant_id: string, location_id: int|null, restaurant_name: string, state: string, return_url: string}
     */
    public function exchangeCode(string $code, string $state): array
    {
        $hash = hash('sha256', $code);

        return DB::transaction(function () use ($hash, $state) {
            $row = AuthCode::query()
                ->where('code_hash', $hash)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (!$row) {
                $exists = AuthCode::where('code_hash', $hash)->first();
                if ($exists?->used_at) {
                    throw new InstallationException('code_already_used', 'Authorization code was already used.');
                }
                if ($exists && $exists->expires_at?->isPast()) {
                    throw new InstallationException('code_expired', 'Authorization code has expired.');
                }
                throw new InstallationException('invalid_code', 'Authorization code is invalid.');
            }

            if (!hash_equals((string) $row->state, $state)) {
                throw new InstallationException('invalid_state', 'State mismatch.');
            }

            $row->used_at = now();
            $row->save();

            $tenant = Tenant::findOrFail($row->tenant_id);
            $location = Location::where('tenant_id', $tenant->id)->first();

            return [
                'pos_tenant_id' => (int) $tenant->id,
                'external_tenant_id' => (string) $tenant->external_tenant_id,
                'location_id' => $location?->location_id,
                'restaurant_name' => (string) $tenant->name,
                'state' => (string) $row->state,
                // Where VoxPilot sends the owner once the install completes: back to this POS.
                // Given server-to-server (secret-authenticated), never taken from the browser.
                'return_url' => admin_url('igniter/voxpilot/integrations'),
            ];
        });
    }

    /**
     * @return array{api_token: string, location_id: int|null, status: string, tenant_id: int}
     */
    public function activate(string $externalTenantId, string $connectionId, string $assistantId): array
    {
        return DB::transaction(function () use ($externalTenantId, $connectionId, $assistantId) {
            $tenant = Tenant::where('external_tenant_id', $externalTenantId)->lockForUpdate()->first();
            if (!$tenant) {
                throw new InstallationException('tenant_not_found', 'POS tenant not found for external_tenant_id.');
            }

            $existing = Installation::where('tenant_id', $tenant->id)->lockForUpdate()->first();
            if ($existing?->isConnected() && $existing->voxpilot_connection_id !== $connectionId) {
                throw new InstallationException('already_connected', 'Tenant already has an active VoxPilot connection.');
            }

            $location = Location::where('tenant_id', $tenant->id)->first();

            // Revoke previous install tokens, then mint a fresh one.
            TenantApiToken::where('tenant_id', $tenant->id)
                ->where('name', self::INSTALL_TOKEN_NAME)
                ->whereNull('revoked_at')
                ->get()
                ->each(fn (TenantApiToken $t) => $t->revoke());

            $tokenResult = TenantApiToken::generateToken(
                tenantId: (int) $tenant->id,
                name: self::INSTALL_TOKEN_NAME,
                defaultLocationId: $location?->location_id,
                createdByUserId: null,
                abilities: ['orders:create', 'menu:read'],
            );

            Installation::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'external_tenant_id' => $externalTenantId,
                    'voxpilot_connection_id' => $connectionId,
                    'assistant_id' => $assistantId,
                    'status' => Installation::STATUS_CONNECTED,
                    'connected_at' => now(),
                    'disconnected_at' => null,
                ]
            );

            return [
                'api_token' => $tokenResult['plain_text'],
                'location_id' => $location?->location_id,
                'status' => Installation::STATUS_CONNECTED,
                'tenant_id' => (int) $tenant->id,
            ];
        });
    }

    public function deactivate(string $externalTenantId): array
    {
        return DB::transaction(function () use ($externalTenantId) {
            $tenant = Tenant::where('external_tenant_id', $externalTenantId)->lockForUpdate()->first();
            if (!$tenant) {
                throw new InstallationException('tenant_not_found', 'POS tenant not found for external_tenant_id.');
            }

            TenantApiToken::where('tenant_id', $tenant->id)
                ->whereNull('revoked_at')
                ->where(function ($q) {
                    $q->where('name', self::INSTALL_TOKEN_NAME)
                        ->orWhere('name', 'VoxPilot Auto-Provisioned');
                })
                ->get()
                ->each(fn (TenantApiToken $t) => $t->revoke());

            $installation = Installation::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'external_tenant_id' => $externalTenantId,
                    'status' => Installation::STATUS_DISCONNECTED,
                    'assistant_id' => null,
                    'voxpilot_connection_id' => null,
                    'disconnected_at' => now(),
                ]
            );

            return [
                'status' => Installation::STATUS_DISCONNECTED,
                'tenant_id' => (int) $tenant->id,
                'installation_id' => (int) $installation->id,
            ];
        });
    }

    public function statusForTenant(int $tenantId): ?Installation
    {
        return Installation::where('tenant_id', $tenantId)->first();
    }
}
