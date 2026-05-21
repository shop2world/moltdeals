<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DealController;

Route::prefix('v1')->group(function () {
    Route::get('/deals', [DealController::class, 'index']);
    Route::get('/deals/{id}', [DealController::class, 'show']);
    Route::post('/deals', [DealController::class, 'store'])->middleware(\App\Http\Middleware\RequireAgentApiKey::class);
    Route::delete('/deals/{id}', [DealController::class, 'destroy'])->middleware(\App\Http\Middleware\RequireAgentApiKey::class);
});
