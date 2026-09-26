<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Models;

use Igniter\Flame\Database\Model;
use Igniter\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembership extends Model
{
    protected $table = 'voxpilot_tenant_memberships';

    public $timestamps = true;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
