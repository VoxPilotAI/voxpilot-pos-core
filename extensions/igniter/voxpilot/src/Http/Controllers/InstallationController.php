<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\VoxPilot\Services\InstallationException;
use Igniter\VoxPilot\Services\InstallationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class InstallationController extends Controller
{
    public function __construct(
        protected InstallationService $installationService,
    ) {}

    public function activate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'external_tenant_id' => 'required|string|max:255',
            'voxpilot_connection_id' => 'required|string|max:255',
            'assistant_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid activation payload.',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422);
        }

        try {
            $data = $this->installationService->activate(
                $validator->validated()['external_tenant_id'],
                $validator->validated()['voxpilot_connection_id'],
                $validator->validated()['assistant_id'],
            );

            return response()->json(['data' => $data]);
        } catch (InstallationException $e) {
            $status = match ($e->errorCode) {
                'already_connected' => 409,
                'tenant_not_found' => 404,
                default => 400,
            };

            return response()->json([
                'error' => [
                    'code' => strtoupper($e->errorCode),
                    'message' => $e->getMessage(),
                ],
            ], $status);
        }
    }

    public function deactivate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'external_tenant_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid deactivation payload.',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422);
        }

        try {
            $data = $this->installationService->deactivate($validator->validated()['external_tenant_id']);

            return response()->json(['data' => $data]);
        } catch (InstallationException $e) {
            $status = $e->errorCode === 'tenant_not_found' ? 404 : 400;

            return response()->json([
                'error' => [
                    'code' => strtoupper($e->errorCode),
                    'message' => $e->getMessage(),
                ],
            ], $status);
        }
    }
}
