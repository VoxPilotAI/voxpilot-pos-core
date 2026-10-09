<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Models;

use Igniter\Flame\Database\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installation extends Model
{
    public const STATUS_NOT_CONNECTED = 'NOT_CONNECTED';

    public const STATUS_INSTALLATION_PENDING = 'INSTALLATION_PENDING';

    public const STATUS_CONNECTED = 'CONNECTED';

    public const STATUS_DISCONNECTED = 'DISCONNECTED';

    public const STATUS_INSTALLATION_FAILED = 'INSTALLATION_FAILED';

    protected $table = 'voxpilot_installations';

    public $timestamps = true;

    protected $fillable = [
        'tenant_id',
        'external_tenant_id',
        'voxpilot_connection_id',
        'assistant_id',
        'status',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }
}
