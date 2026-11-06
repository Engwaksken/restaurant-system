<?php

/*
|--------------------------------------------------------------------------
| REAL ORDER ADMIN ROUTES
|--------------------------------------------------------------------------
|
| Add these routes inside the existing:
|
| Route::middleware('auth')
|     ->prefix('admin')
|     ->name('admin.')
|     ->group(function () { ... });
|
| IMPORTANT:
| Put these BEFORE:
|     require __DIR__ . '/admin-fallback.php';
|
*/

Route::get('/orders/dine-in', [OrderController::class, 'dineIn'])
    ->name('orders.dine-in');

Route::get('/orders/takeaway', [OrderController::class, 'takeaway'])
    ->name('orders.takeaway');

Route::get('/orders/delivery', [OrderController::class, 'delivery'])
    ->name('orders.delivery');

Route::get('/orders/online', [OrderController::class, 'online'])
    ->name('orders.online');

Route::get('/orders/cancelled', [OrderController::class, 'cancelled'])
    ->name('orders.cancelled');

Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])
    ->name('orders.status.update');

Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel'])
    ->name('orders.cancel');
