<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Local\Models\Location;
use Igniter\User\Models\User;
use Igniter\User\Models\UserRole;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    private const VOXPILOT_MANAGE_PERMISSION = 'Igniter.VoxPilot.Manage';

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
            // SPEC-011: mint API token only on Install (activate), not at provision time.

            return [
                'provisioned' => true,
                'tenant_id' => $tenant->id,
                'external_tenant_id' => $externalTenantId,
                'location_id' => $location->location_id,
                'admin_user_id' => $adminUser->user_id,
                'api_token' => null,
                'base_url' => config('app.url'),
            ];
        });
    }

    protected function buildExistingResponse(Tenant $tenant): array
    {
        $location = Location::where('tenant_id', $tenant->id)->first();

        return [
            'provisioned' => false,
            'tenant_id' => $tenant->id,
            'external_tenant_id' => $tenant->external_tenant_id,
            'location_id' => $location?->location_id,
            'admin_user_id' => $tenant->memberships()->where('role', 'owner')->value('user_id'),
            'api_token' => null,
            'base_url' => config('app.url'),
            'message' => 'Tenant already exists. Connect with VoxPilot from the admin panel to install.',
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
            $candidateSlug = $slug.'-'.$counter;
        }

        $settings = [
            'provisioned_by' => 'voxpilot',
            'provisioned_at' => now()->toISOString(),
        ];

        if (!empty($payload['webhook_callback_url'])) {
            $settings['webhook_callback_url'] = $payload['webhook_callback_url'];
        }

        return Tenant::create([
            'name' => $name,
            'slug' => $candidateSlug,
            'external_tenant_id' => $externalTenantId,
            'status' => 'active',
            'settings' => $settings,
        ]);
    }

    protected function createAdminUser(array $payload, Tenant $tenant): User
    {
        $email = $payload['admin_email'];
        $ownerRole = $this->ensureOwnerRoleWithVoxPilotPermission();

        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            $this->assignOwnerRole($existingUser, $ownerRole);

            return $existingUser;
        }

        $user = new User;
        $user->name = $payload['admin_name'] ?? $payload['company_name'] ?? 'Admin';
        $user->email = $email;
        $user->username = Str::slug($email, '_');
        $user->password = Hash::make(Str::random(32));
        $user->super_user = false;
        $user->is_activated = true;
        $user->status = true;
        $this->assignOwnerRole($user, $ownerRole);
        $user->save();

        return $user;
    }

    /**
     * Restaurant owners need Igniter.VoxPilot.Manage to see Tools → VoxPilot (Connect).
     * TastyIgniter stores role permissions as a map: permission => 1.
     */
    protected function ensureOwnerRoleWithVoxPilotPermission(): ?UserRole
    {
        $ownerRole = UserRole::query()->where('code', 'owner')->first()
            ?? UserRole::query()->find(1);

        if (!$ownerRole) {
            return null;
        }

        $perms = is_array($ownerRole->permissions) ? $ownerRole->permissions : [];
        if (!array_key_exists(self::VOXPILOT_MANAGE_PERMISSION, $perms)) {
            $perms[self::VOXPILOT_MANAGE_PERMISSION] = 1;
            $ownerRole->permissions = $perms;
            $ownerRole->save();
        }

        return $ownerRole;
    }

    protected function assignOwnerRole(User $user, ?UserRole $ownerRole): void
    {
        if (!$ownerRole) {
            return;
        }

        if ((int) $user->user_role_id !== (int) $ownerRole->user_role_id) {
            $user->user_role_id = $ownerRole->user_role_id;
            if ($user->exists) {
                $user->save();
            }
        }
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

        $location = new Location;
        $location->location_name = $locationName;
        $location->tenant_id = $tenant->id;
        $location->location_email = $payload['admin_email'] ?? '';
        $location->location_telephone = $payload['phone'] ?? '';
        $location->location_status = true;
        $location->save();

        return $location;
    }
}
