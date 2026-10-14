<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Local\Models\Location;
use Igniter\User\Models\User;
use Igniter\User\Models\UserRole;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Support\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * What a provisioned restaurant owner may do: connect VoxPilot and run their own restaurant —
     * orders, menu (dishes, categories, mealtimes, ingredients, stock), locations, staff, customers,
     * coupons, reviews and the dashboard. Every one of these is scoped to the restaurant; the
     * platform-wide settings stay with super users (PlatformPermissions).
     */
    public const OWNER_PERMISSIONS = [
        'Igniter.VoxPilot.Manage',
        'Admin.Dashboard',
        'Admin.Orders',
        'Admin.AssignOrders',
        'Admin.Menus',
        'Admin.Categories',
        'Admin.Mealtimes',
        'Admin.Ingredients',
        'Admin.Allergens',
        'Admin.Inventory',
        'Admin.Locations',
        'Admin.Staffs',
        'Admin.Customers',
        'Admin.Coupons',
        'Admin.Reviews',
    ];

    public function provision(array $payload): array
    {
        $externalTenantId = $payload['external_tenant_id'];

        $existing = Tenant::where('external_tenant_id', $externalTenantId)->first();
        if ($existing) {
            return $this->buildExistingResponse($existing);
        }

        $ownerCreated = false;
        $result = DB::transaction(function () use ($payload, $externalTenantId, &$ownerCreated) {
            $tenant = $this->createTenant($payload, $externalTenantId);
            [$adminUser, $ownerCreated] = $this->createAdminUser($payload, $tenant);
            if ($ownerCreated) {
                $this->applyOwnerLanguage($adminUser, $payload['locale'] ?? null);
            }
            $this->createMembership($tenant, $adminUser);
            $location = $this->createLocation($payload, $tenant);
            // TastyIgniter shows a staff member only the orders of their assigned locations.
            $this->assignOwnerToLocation($adminUser, $location);
            // SPEC-011: mint API token only on Install (activate), not at provision time.

            return [
                'provisioned' => true,
                'tenant_id' => $tenant->id,
                'external_tenant_id' => $externalTenantId,
                'location_id' => $location->location_id,
                'admin_user_id' => $adminUser->user_id,
                'api_token' => null,
                'base_url' => config('app.url'),
                'pos_admin_url' => $this->adminUrl(),
                'owner_invite_sent' => false,
            ];
        });

        // The owner is created with a random password nobody knows. The standard TastyIgniter staff
        // invite (set-your-password link) lets them sign in to the admin and click Connect with
        // VoxPilot. Sent after commit, and only for an owner created here; a mail failure never
        // undoes the provisioning.
        if ($ownerCreated && ($payload['send_owner_invite'] ?? true) && config('voxpilot.send_owner_invite', true)) {
            $result['owner_invite_sent'] = $this->sendOwnerInvite((int) $result['admin_user_id']);
        }

        return $result;
    }

    /**
     * The owner's language (VoxPilot account language) as their TastyIgniter language: the admin
     * locale and every staff email (invite, password reset) follow it. English when none is sent.
     */
    protected function applyOwnerLanguage(User $user, ?string $locale): void
    {
        $user->language_id = Locale::language(Locale::normalize($locale))->getKey();
        $user->save();
    }

    /** TastyIgniter's staff invite, sent in the owner's language by LocalizedMailHelper. */
    protected function sendOwnerInvite(int $userId): bool
    {
        try {
            User::findOrFail($userId)->sendInvite();

            return true;
        } catch (\Throwable $e) {
            Log::warning('VoxPilot provisioning: owner invite email failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function adminUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/admin';
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
            'pos_admin_url' => $this->adminUrl(),
            'owner_invite_sent' => false,
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

    /** @return array{0: User, 1: bool} the owner and whether it was created now */
    protected function createAdminUser(array $payload, Tenant $tenant): array
    {
        $email = $payload['admin_email'];
        $ownerRole = $this->ensureOwnerRoleWithVoxPilotPermission();

        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            $this->assignOwnerRole($existingUser, $ownerRole);

            return [$existingUser, false];
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

        return [$user, true];
    }

    /**
     * The shared owner role holds OWNER_PERMISSIONS. TastyIgniter stores role permissions as a map:
     * permission => 1.
     */
    public function ensureOwnerRoleWithVoxPilotPermission(): ?UserRole
    {
        $ownerRole = UserRole::query()->where('code', 'owner')->first()
            ?? UserRole::query()->find(1);

        if (!$ownerRole) {
            return null;
        }

        // Add what is missing; never drop permissions the role already has.
        $perms = is_array($ownerRole->permissions) ? $ownerRole->permissions : [];
        $missing = array_diff(self::OWNER_PERMISSIONS, array_keys($perms));
        if ($missing) {
            foreach ($missing as $permission) {
                $perms[$permission] = 1;
            }
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

    protected function assignOwnerToLocation(User $user, Location $location): void
    {
        $user->locations()->syncWithoutDetaching([$location->location_id]);
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
