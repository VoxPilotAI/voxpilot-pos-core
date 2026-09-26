<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Models;

use Igniter\Flame\Database\Model;
use Igniter\Local\Models\Location;
use Igniter\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $table = 'voxpilot_tenants';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'slug',
        'external_tenant_id',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'tenant_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'voxpilot_tenant_memberships', 'tenant_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'tenant_id');
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(TenantApiToken::class, 'tenant_id');
    }

    public function activeApiTokens(): HasMany
    {
        return $this->apiTokens()->whereNull('revoked_at');
    }
}
