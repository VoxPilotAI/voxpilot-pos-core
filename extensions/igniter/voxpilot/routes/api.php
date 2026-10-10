<?php

declare(strict_types=1);

use Igniter\VoxPilot\Http\Controllers\VoxPilotMenuController;
use Igniter\VoxPilot\Http\Controllers\VoxPilotOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/orders/quote', [VoxPilotOrderController::class, 'quote'])
    ->name('voxpilot.orders.quote');

Route::post('/orders/{externalOrderId}/cancel', [VoxPilotOrderController::class, 'cancel'])
    ->where('externalOrderId', '[A-Za-z0-9_-]{1,128}')
    ->name('voxpilot.orders.cancel');

Route::put('/orders/{externalOrderId}/transcript', [VoxPilotOrderController::class, 'transcript'])
    ->where('externalOrderId', '[A-Za-z0-9_-]{1,128}')
    ->name('voxpilot.orders.transcript');

Route::put('/orders/{externalOrderId}', [VoxPilotOrderController::class, 'update'])
    ->where('externalOrderId', '[A-Za-z0-9_-]{1,128}')
    ->name('voxpilot.orders.update');

Route::post('/orders', [VoxPilotOrderController::class, 'store'])
    ->name('voxpilot.orders.store');

Route::get('/menu', [VoxPilotMenuController::class, 'index'])
    ->name('voxpilot.menu.index');
