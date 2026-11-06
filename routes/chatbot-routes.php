<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ChatbotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Restaurant Chatbot Routes
|--------------------------------------------------------------------------
|
| Add these INSIDE your existing:
|
| Route::middleware('auth')
|     ->prefix('admin')
|     ->name('admin.')
|     ->group(function () {
|
*/

Route::get('/chatbot', [ChatbotController::class, 'index'])
    ->name('chatbot.index');

Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])
    ->middleware('throttle:30,1')
    ->name('chatbot.ask');
