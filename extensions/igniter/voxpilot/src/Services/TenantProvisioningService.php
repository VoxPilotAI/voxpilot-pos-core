<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Local\Models\Location;
use Igniter\User\Models\User;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantProvisioningService
{
    public function provision(array $payload): array
    {
        $externalTenantId = $payload['external_tenant_id'];

        $existing = Tenant::where('external_tenant_id', $externalTenantId)->first();
        if ($existing) {
            return $this->buildExistingResponse($existing);
        }

        return DB::transaction(function () use ($payload, $externalTenantId) {
            $tenant = $this->createTenant($payload, $externalTenantId);
            $adminUser = $this->createAdminUser($payload, $tenant);
            $this->createMembership($tenant, $adminUser);
            $location = $this->createLocation($payload, $tenant);
            $tokenResult = $this->createApiToken($tenant, $location, $adminUser);

            return [
                'provisioned' => true,
                'tenant_id' => $tenant->id,
                'external_tenant_id' => $externalTenantId,
                'location_id' => $location->location_id,
                'admin_user_id' => $adminUser->user_id,
                'api_token' => $tokenResult['plain_text'],
                'base_url' => config('app.url'),
            ];
        });
    }

    protected function buildExistingResponse(Tenant $tenant): array
    {
        $location = Location::where('tenant_id', $tenant->id)->first();
        $activeToken = $tenant->activeApiTokens()->first();

        return [
            'provisioned' => false,
            'tenant_id' => $tenant->id,
            'external_tenant_id' => $tenant->external_tenant_id,
            'location_id' => $location?->location_id,
            'admin_user_id' => $tenant->memberships()->where('role', 'owner')->value('user_id'),
            'api_token' => null,
            'base_url' => config('app.url'),
            'message' => 'Tenant already exists. API token cannot be retrieved — generate a new one from the admin panel if needed.',
        ];
    }

    protected function createTenant(array $payload, string $externalTenantId): Tenant
    {
        $name = $payload['company_name'] ?? $payload['tenant_name'] ?? 'Tenant';
        $slug = Str::slug($name);

        $counter = 0;
        $candidateSlug = $slug;
        while (Tenant::where('slug', $candidateSlug)->exists()) {
            $counter++;
            $candidateSlug = $slug . '-' . $counter;
        }

        return Tenant::create([
            'name' => $name,
            'slug' => $candidateSlug,
            'external_tenant_id' => $externalTenantId,
            'status' => 'active',
            'settings' => [
                'provisioned_by' => 'voxpilot',
                'provisioned_at' => now()->toISOString(),
            ],
        ]);
    }

    protected function createAdminUser(array $payload, Tenant $tenant): User
    {
        $email = $payload['admin_email'];

        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            return $existingUser;
        }

        $user = new User();
        $user->name = $payload['admin_name'] ?? $payload['company_name'] ?? 'Admin';
        $user->email = $email;
        $user->username = Str::slug($email, '_');
        $user->password = Hash::make(Str::random(32));
        $user->super_user = false;
        $user->is_activated = true;
        $user->status = true;
        $user->save();

        return $user;
    }

    protected function createMembership(Tenant $tenant, User $user): void
    {
        $exists = TenantMembership::where('tenant_id', $tenant->id)
            ->where('user_id', $user->user_id)
            ->exists();

        if ($exists) {
            return;
        }

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->user_id,
            'role' => 'owner',
        ]);
    }

    protected function createLocation(array $payload, Tenant $tenant): Location
    {
        $locationName = $payload['location_name'] ?? $payload['company_name'] ?? $tenant->name;

        $location = new Location();
        $location->location_name = $locationName;
        $location->tenant_id = $tenant->id;
        $location->location_email = $payload['admin_email'] ?? '';
        $location->location_telephone = $payload['phone'] ?? '';
        $location->location_status = true;
        $location->save();

        return $location;
    }

    protected function createApiToken(Tenant $tenant, Location $location, User $adminUser): array
    {
        return TenantApiToken::generateToken(
            tenantId: $tenant->id,
            name: 'VoxPilot Auto-Provisioned',
            defaultLocationId: $location->location_id,
            createdByUserId: $adminUser->user_id,
            abilities: ['orders:create'],
        );
    }
}
