<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Models;

use Igniter\Flame\Database\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthCode extends Model
{
    protected $table = 'voxpilot_auth_codes';

    public $timestamps = true;

    protected $fillable = [
        'tenant_id',
        'code_hash',
        'state',
        'redirect_uri',
        'expires_at',
        'used_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
