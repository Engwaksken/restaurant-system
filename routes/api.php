<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ChatbotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {
    Route::post('/chat/message', [ChatbotController::class, 'message'])
        ->middleware('throttle:30,1')
        ->name('api.v1.chat.message');
});
