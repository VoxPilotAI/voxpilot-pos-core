<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Models;

use Igniter\Cart\Models\Order;
use Igniter\Flame\Database\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoxPilotOrderMetadata extends Model
{
    protected $table = 'voxpilot_order_metadata';

    public $timestamps = true;

    protected $fillable = [
        'order_id',
        'tenant_id',
        'location_id',
        'external_order_id',
        'call_sid',
        'source',
        'transcript',
        'raw_payload',
        'idempotency_key',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'order_id' => 'integer',
        'tenant_id' => 'integer',
        'location_id' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
