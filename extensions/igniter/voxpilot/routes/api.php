<?php

declare(strict_types=1);

use Igniter\VoxPilot\Http\Controllers\VoxPilotMenuController;
use Igniter\VoxPilot\Http\Controllers\VoxPilotOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/orders/quote', [VoxPilotOrderController::class, 'quote'])
    ->name('voxpilot.orders.quote');

Route::post('/orders', [VoxPilotOrderController::class, 'store'])
    ->name('voxpilot.orders.store');

Route::get('/menu', [VoxPilotMenuController::class, 'index'])
    ->name('voxpilot.menu.index');
