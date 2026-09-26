<?php

declare(strict_types=1);

use Igniter\VoxPilot\Http\Controllers\VoxPilotOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/orders', [VoxPilotOrderController::class, 'store'])
    ->name('voxpilot.orders.store');
