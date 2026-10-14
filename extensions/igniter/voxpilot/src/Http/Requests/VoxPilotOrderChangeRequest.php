<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Requests;

/** PUT /api/voxpilot/orders/{external_order_id}: the caller's new item list (SPEC-005). */
class VoxPilotOrderChangeRequest extends VoxPilotOrderRequest
{
    public function rules(): array
    {
        return [
            'fulfillment.type' => ['nullable', 'string', 'in:pickup,delivery'],
            'fulfillment.address' => ['nullable', 'required_if:fulfillment.type,delivery', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
