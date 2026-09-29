<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\VoxPilot\Services\InstallationException;
use Igniter\VoxPilot\Services\InstallationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class OAuthController extends Controller
{
    public function __construct(
        protected InstallationService $installationService,
    ) {}

    public function token(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|min:16|max:128',
            'state' => 'required|string|min:8|max:128',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid OAuth token exchange payload.',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422);
        }

        try {
            $data = $this->installationService->exchangeCode(
                $validator->validated()['code'],
                $validator->validated()['state'],
            );

            return response()->json(['data' => $data]);
        } catch (InstallationException $e) {
            $status = match ($e->errorCode) {
                'code_expired', 'code_already_used', 'invalid_code', 'invalid_state' => 400,
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
}
