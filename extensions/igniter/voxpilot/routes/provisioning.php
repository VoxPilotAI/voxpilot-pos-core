<?php

declare(strict_types=1);

use Igniter\VoxPilot\Http\Controllers\ProvisioningController;
use Illuminate\Support\Facades\Route;

Route::post('/tenants', [ProvisioningController::class, 'store'])
    ->name('voxpilot.provision.tenants');
