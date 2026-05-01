<?php

use App\Http\Controllers\DeployController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::middleware('artifacthost.apikey')->prefix('v1')->group(function () {
    Route::get('/sites', [SiteController::class, 'index'])
        ->middleware('throttle:artifacthost-reads');

    Route::post('/sites', [DeployController::class, 'store'])
        ->middleware('throttle:artifacthost-writes');

    Route::put('/sites/{subdomain}', [DeployController::class, 'update'])
        ->middleware('throttle:artifacthost-writes');

    Route::delete('/sites/{subdomain}', [SiteController::class, 'destroy'])
        ->middleware('throttle:artifacthost-writes');

    Route::get('/sites/{subdomain}/logs', [SiteController::class, 'logs'])
        ->middleware('throttle:artifacthost-reads');
});

Route::get('/health', fn () => response()->json(['ok' => true]));
