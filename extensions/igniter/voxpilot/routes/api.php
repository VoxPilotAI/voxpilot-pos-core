<?php

declare(strict_types=1);

use Igniter\VoxPilot\Http\Controllers\VoxPilotMenuController;
use Igniter\VoxPilot\Http\Controllers\VoxPilotOrderController;
use Igniter\VoxPilot\Http\Middleware\RequireTokenAbility;
use Illuminate\Support\Facades\Route;

// Orders: the token VoxPilot got at install (orders:create) places, quotes, changes and cancels its
// own orders. The menu: menu:read (or the same order token).
Route::middleware(RequireTokenAbility::class.':orders:create')->group(function (): void {
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

});

Route::get('/menu', [VoxPilotMenuController::class, 'index'])
    ->middleware(RequireTokenAbility::class.':menu:read,orders:create')
    ->name('voxpilot.menu.index');
