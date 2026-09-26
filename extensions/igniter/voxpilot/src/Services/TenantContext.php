<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\VoxPilot\Models\Tenant;

class TenantContext
{
    protected ?Tenant $tenant = null;

    protected ?int $locationId = null;

    public function set(Tenant $tenant, ?int $locationId = null): void
    {
        $this->tenant = $tenant;
        $this->locationId = $locationId;
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
