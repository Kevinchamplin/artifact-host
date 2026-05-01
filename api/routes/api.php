<?php

use App\Http\Controllers\DeployController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::middleware('artifacthost.apikey')->prefix('v1')->group(function () {
    Route::get('/sites', [SiteController::class, 'index']);
    Route::post('/sites', [DeployController::class, 'store']);
    Route::put('/sites/{subdomain}', [DeployController::class, 'update']);
    Route::delete('/sites/{subdomain}', [SiteController::class, 'destroy']);
    Route::get('/sites/{subdomain}/logs', [SiteController::class, 'logs']);
});

Route::get('/health', fn () => response()->json(['ok' => true]));
