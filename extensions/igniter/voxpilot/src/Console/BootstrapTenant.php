<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Console;

use Igniter\Local\Models\Location;
use Igniter\User\Models\User;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BootstrapTenant extends Command
{
    protected $signature = 'voxpilot:bootstrap-tenant
                            {--name= : Tenant name (default: env VOXPILOT_DEFAULT_TENANT_NAME or "Default Tenant")}';

    protected $description = 'Create or verify the default VoxPilot tenant, assign existing admin users and locations';

    public function handle(): int
    {
        $tenantName = $this->option('name')
            ?: env('VOXPILOT_DEFAULT_TENANT_NAME', 'Default Tenant');

        $tenant = $this->ensureTenant($tenantName);
        $this->attachLocations($tenant);
        $this->attachAdminUsers($tenant);

        $this->info("Tenant \"{$tenant->name}\" (ID: {$tenant->id}) is ready.");

        return self::SUCCESS;
    }

    protected function ensureTenant(string $name): Tenant
    {
        $slug = Str::slug($name);

        $tenant = Tenant::where('slug', $slug)->first();
        if ($tenant) {
            $this->info("Tenant \"{$name}\" already exists (ID: {$tenant->id}).");
            return $tenant;
        }

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);

        $this->info("Created tenant \"{$name}\" (ID: {$tenant->id}).");
        return $tenant;
    }

    protected function attachLocations(Tenant $tenant): void
    {
        $unassigned = Location::whereNull('tenant_id')->get();

        if ($unassigned->isEmpty()) {
            $this->info('All locations already assigned to a tenant.');
            return;
        }

        foreach ($unassigned as $location) {
            $location->tenant_id = $tenant->id;
            $location->save();
            $this->info("  Assigned location \"{$location->location_name}\" (ID: {$location->location_id}) to tenant.");
        }
    }

    protected function attachAdminUsers(Tenant $tenant): void
    {
        $users = User::whereIsEnabled()->get();

        foreach ($users as $user) {
            $exists = TenantMembership::where('tenant_id', $tenant->id)
                ->where('user_id', $user->user_id)
                ->exists();

            if ($exists) {
                continue;
            }

            TenantMembership::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->user_id,
                'role' => $user->super_user ? 'owner' : 'admin',
            ]);

            $this->info("  Assigned user \"{$user->name}\" (ID: {$user->user_id}) as {$this->getRoleLabel($user)}.");
        }
    }

    protected function getRoleLabel(User $user): string
    {
        return $user->super_user ? 'owner' : 'admin';
    }
}
