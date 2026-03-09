<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\ChatController;
use App\Http\Middleware\IdentifyTenant;

Route::middleware([IdentifyTenant::class])->group(function () {
    // Facebook Webhook
    Route::get('/webhook', [WebhookController::class, 'verify']);
    Route::post('/webhook', [WebhookController::class, 'handleEvent']);

    // Chat API (DEV mode only)
    if (config('agent.mode') === 'DEV') {
        Route::post('/chat', [ChatController::class, 'handleChat']);
        Route::get('/chat/poll', [ChatController::class, 'poll']);
    }
});
