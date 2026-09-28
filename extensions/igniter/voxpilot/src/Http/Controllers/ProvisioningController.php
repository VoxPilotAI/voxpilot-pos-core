<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\VoxPilot\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProvisioningController extends Controller
{
    public function __construct(
        protected TenantProvisioningService $provisioningService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'external_tenant_id' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_name' => 'nullable|string|max:255',
            'location_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'webhook_callback_url' => 'nullable|url:http,https|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid provisioning payload.',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422);
        }

        try {
            $result = $this->provisioningService->provision($validator->validated());

            Log::channel('single')->info('Tenant provisioned', [
                'external_tenant_id' => $result['external_tenant_id'],
                'tenant_id' => $result['tenant_id'],
                'provisioned' => $result['provisioned'],
            ]);

            $status = $result['provisioned'] ? 201 : 200;

            return response()->json(['data' => $result], $status);
        } catch (\Throwable $e) {
            Log::channel('single')->error('Tenant provisioning failed', [
                'external_tenant_id' => $request->input('external_tenant_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'PROVISIONING_FAILED',
                    'message' => 'Tenant provisioning failed.',
                ],
            ], 500);
        }
    }
}
