<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoxPilotOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_order_id' => ['required', 'string', 'max:255'],
            'call_sid' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'in:voice,chat,web,api'],
            'location_id' => ['nullable', 'integer'],
            'customer.name' => ['required', 'string', 'max:100'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.email' => ['nullable', 'email', 'max:96'],
            'fulfillment.type' => ['required', 'string', 'in:pickup,delivery'],
            'fulfillment.requested_time' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transcript' => ['nullable', 'string'],
            'raw' => ['nullable', 'array'],
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed.',
                'details' => $validator->errors()->toArray(),
            ],
        ], 422));
    }
}
