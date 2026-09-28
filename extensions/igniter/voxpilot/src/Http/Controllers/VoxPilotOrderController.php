<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\VoxPilot\Http\Requests\VoxPilotOrderRequest;
use Igniter\VoxPilot\Services\OrderIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class VoxPilotOrderController extends Controller
{
    public function __construct(
        protected OrderIngestionService $ingestionService,
    ) {}

    public function store(VoxPilotOrderRequest $request): JsonResponse
    {
        $tenant = $request->attributes->get('voxpilot_tenant');
        $token = $request->attributes->get('voxpilot_token');

        try {
            $result = $this->ingestionService->ingest(
                $request->validated(),
                $tenant,
                $token,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $e->response ?? response()->json([
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }

        $order = $result['order'];
        $statusCode = $result['created'] ? 201 : 200;

        $data = [
            'order_id' => $order->order_id,
            'external_order_id' => $result['metadata']->external_order_id,
            'status' => $order->status?->status_name ?? 'pending',
            'location' => [
                'id' => $order->location_id,
                'name' => $order->location?->location_name ?? '',
            ],
            'items_count' => $order->total_items,
            'order_total' => $order->order_total,
            'created_at' => $order->created_at?->toIso8601String(),
            'is_duplicate' => !$result['created'],
        ];

        if ($result['created']) {
            $data['price_mismatch'] = $result['price_mismatch'] ?? false;
            $data['unmapped'] = array_map(
                fn($i) => ['name' => $i['name'] ?? '?', 'quantity' => $i['quantity'] ?? 1],
                $result['unmapped'] ?? []
            );
        }

        return response()->json(['data' => $data], $statusCode);
    }
}
