<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Http\Requests\VoxPilotOrderChangeRequest;
use Igniter\VoxPilot\Http\Requests\VoxPilotOrderRequest;
use Igniter\VoxPilot\Http\Requests\VoxPilotQuoteRequest;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Igniter\VoxPilot\Services\OrderChangeService;
use Igniter\VoxPilot\Services\OrderIngestionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class VoxPilotOrderController extends Controller
{
    public function __construct(
        protected OrderIngestionService $ingestionService,
    ) {}

    /** POST /api/voxpilot/orders/quote — the POS total for an order still being taken (SPEC-002). */
    public function quote(VoxPilotQuoteRequest $request): JsonResponse
    {
        try {
            $quote = $this->ingestionService->quote(
                $request->validated(),
                $request->attributes->get('voxpilot_tenant'),
                $request->attributes->get('voxpilot_token'),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $e->response ?? response()->json(['error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]], 422);
        }

        return response()->json(['data' => $quote]);
    }

    /** POST /api/voxpilot/orders/{external_order_id}/cancel — the caller cancels by phone (SPEC-005). */
    public function cancel(Request $request, string $externalOrderId): JsonResponse
    {
        $changes = new OrderChangeService();
        $order = $changes->find($request->attributes->get('voxpilot_tenant'), $externalOrderId);
        if ($error = $this->changeError($changes, $order)) {
            return $error;
        }
        $changes->cancel($order);

        return response()->json(['data' => $this->changedOrder($order->fresh(['menus', 'status']))]);
    }

    /** PUT /api/voxpilot/orders/{external_order_id} — the caller changes the items by phone (SPEC-005). */
    public function update(VoxPilotOrderChangeRequest $request, string $externalOrderId): JsonResponse
    {
        $tenant = $request->attributes->get('voxpilot_tenant');
        $changes = new OrderChangeService();
        $order = $changes->find($tenant, $externalOrderId);
        if ($error = $this->changeError($changes, $order)) {
            return $error;
        }

        $result = $changes->replaceItems($order, $request->validated(), $tenant, $request->attributes->get('voxpilot_token'));
        if (isset($result['error'])) {
            return response()->json(['error' => [
                'code' => $result['error'],
                'message' => 'Some items are not on the menu or not available now.',
                'unmapped' => $result['unmapped'],
                'unavailable' => $result['unavailable'],
            ]], 422);
        }

        return response()->json(['data' => $this->changedOrder($result['order'])]);
    }

    /**
     * PUT /api/voxpilot/orders/{external_order_id}/transcript — the call's conversation, sent by
     * VoxPilot when the call ends (the order itself is sent mid-call), shown in the order modal.
     */
    public function transcript(Request $request, string $externalOrderId): JsonResponse
    {
        $turns = collect((array) $request->input('turns', []))
            ->filter(fn ($t) => is_array($t) && in_array($t['role'] ?? null, ['caller', 'assistant'], true) && is_string($t['content'] ?? null))
            ->map(fn ($t) => ['role' => $t['role'], 'content' => mb_substr(trim($t['content']), 0, 2000)])
            ->filter(fn ($t) => $t['content'] !== '')
            ->take(200)
            ->values();
        if ($turns->isEmpty()) {
            return response()->json(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'turns are required']], 422);
        }

        $updated = VoxPilotOrderMetadata::where('tenant_id', $request->attributes->get('voxpilot_tenant')->id)
            ->where('external_order_id', $externalOrderId)
            ->update(['transcript' => json_encode($turns->all(), JSON_UNESCAPED_UNICODE)]);

        return $updated
            ? response()->json(['data' => ['turns' => $turns->count()]])
            : response()->json(['error' => ['code' => OrderChangeService::NOT_FOUND, 'message' => 'Order not found.']], 404);
    }

    protected function changeError(OrderChangeService $changes, ?Order $order): ?JsonResponse
    {
        if (!$order) {
            return response()->json(['error' => ['code' => OrderChangeService::NOT_FOUND, 'message' => 'Order not found.']], 404);
        }
        if (!$changes->canChange($order)) {
            return response()->json(['error' => [
                'code' => OrderChangeService::LOCKED,
                'message' => 'The kitchen already accepted this order.',
                'status' => $order->status?->status_name,
            ]], 409);
        }

        return null;
    }

    protected function changedOrder(Order $order): array
    {
        return [
            'order_id' => $order->order_id,
            'status' => $order->status?->status_name,
            'order_total' => (float) $order->order_total,
            'items' => $order->menus->map(fn ($m) => ['name' => $m->name, 'quantity' => (int) $m->quantity, 'subtotal' => (float) $m->subtotal])->values(),
        ];
    }

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
