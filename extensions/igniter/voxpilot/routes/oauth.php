<?php

declare(strict_types=1);

use Igniter\VoxPilot\Http\Controllers\InstallationController;
use Igniter\VoxPilot\Http\Controllers\OAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/oauth/token', [OAuthController::class, 'token'])
    ->name('voxpilot.oauth.token');

Route::post('/installations/activate', [InstallationController::class, 'activate'])
    ->name('voxpilot.installations.activate');

Route::post('/installations/deactivate', [InstallationController::class, 'deactivate'])
    ->name('voxpilot.installations.deactivate');
