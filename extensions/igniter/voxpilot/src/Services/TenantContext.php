<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\User\Models\User;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantMembership;

class TenantContext
{
    protected ?Tenant $tenant = null;

    protected ?int $locationId = null;

    protected ?string $role = null;

    public function set(Tenant $tenant, ?int $locationId = null): void
    {
        $this->tenant = $tenant;
        $this->locationId = $locationId;
    }

    public function resolveForAdmin(User $user): ?Tenant
    {
        if ($this->tenant) {
            return $this->tenant;
        }

        $membership = TenantMembership::with('tenant')
            ->where('user_id', $user->user_id)
            ->first();

        if ($membership && $membership->tenant) {
            $this->tenant = $membership->tenant;
            $this->role = $membership->role;
        }

        return $this->tenant;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function locationId(): ?int
    {
        return $this->locationId;
    }

    public function isActive(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->locationId = null;
    }
}
