<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Requests;

/** POST /api/voxpilot/orders/quote: the items and fulfillment of an order still being taken. */
class VoxPilotQuoteRequest extends VoxPilotOrderRequest
{
    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer'],
            'fulfillment.type' => ['required', 'string', 'in:pickup,delivery'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
