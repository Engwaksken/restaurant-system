<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ChatbotController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PublicOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public / Customer Routes
|--------------------------------------------------------------------------
|
| Customers browse menu items and place orders from the public website.
|
*/

Route::get(
    '/',
    [HomeController::class, 'index']
)->name('home');


Route::prefix('order')
    ->name('order.')
    ->group(function (): void {

        Route::post(
            '/',
            [PublicOrderController::class, 'store']
        )
            ->middleware('throttle:20,1')
            ->name('store');


        Route::get(
            '/success/{order}',
            [PublicOrderController::class, 'success']
        )->middleware('throttle:60,1')->name('success');


        Route::get(
            '/track/{order}',
            [PublicOrderController::class, 'track']
        )->middleware('throttle:60,1')->name('track');
    });


/*
|--------------------------------------------------------------------------
| Staff / Administrator Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')
    ->prefix('admin')
    ->group(function (): void {

        Route::get(
            '/login',
            [AdminLoginController::class, 'showLoginForm']
        )->name('login');


        Route::post(
            '/login',
            [AdminLoginController::class, 'login']
        )
            ->middleware('throttle:10,1')
            ->name('admin.login.submit');
    });


Route::post(
    '/admin/logout',
    [AdminLoginController::class, 'logout']
)
    ->middleware('auth')
    ->name('admin.logout');


/*
|--------------------------------------------------------------------------
| Main Administration Routes
|--------------------------------------------------------------------------
|
| All concrete admin routes belong in this single authenticated /admin group.
|
*/

Route::middleware('auth')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard',
            DashboardController::class
        )->name('dashboard');


        /*
        |--------------------------------------------------------------------------
        | Menu Items
        |--------------------------------------------------------------------------
        */

        Route::resource(
            'menu-items',
            MenuItemController::class
        )->except([
            'create',
            'show',
            'edit',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Point of Sale
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/pos',
            [PosController::class, 'index']
        )->name('pos.index');


        Route::post(
            '/pos/checkout',
            [PosController::class, 'checkout']
        )->name('pos.checkout');


        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        |
        | Keep the named/static order routes BEFORE /orders/{order}.
        |
        */

        Route::get(
            '/orders/dine-in',
            [OrderController::class, 'dineIn']
        )->name('orders.dine-in');


        Route::get(
            '/orders/takeaway',
            [OrderController::class, 'takeaway']
        )->name('orders.takeaway');


        Route::get(
            '/orders/delivery',
            [OrderController::class, 'delivery']
        )->name('orders.delivery');


        Route::get(
            '/orders/online',
            [OrderController::class, 'online']
        )->name('orders.online');


        Route::get(
            '/orders/cancelled',
            [OrderController::class, 'cancelled']
        )->name('orders.cancelled');


        Route::patch(
            '/orders/{order}/status',
            [OrderController::class, 'updateStatus']
        )->name('orders.status.update');


        Route::patch(
            '/orders/{order}/cancel',
            [OrderController::class, 'cancel']
        )->name('orders.cancel');


        /*
        |--------------------------------------------------------------------------
        | Generic Order Routes
        |--------------------------------------------------------------------------
        */

        Route::resource(
            'orders',
            OrderController::class
        )->only([
            'index',
            'show',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Order Payments
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/orders/{order}/payments',
            [PaymentController::class, 'store']
        )->name('orders.payments.store');


        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/reports/sales.csv',
            [ReportController::class, 'salesCsv']
        )->name('reports.sales.csv');


        Route::get(
            '/reports/sales.pdf',
            [ReportController::class, 'salesPdf']
        )->name('reports.sales.pdf');


        /*
        |--------------------------------------------------------------------------
        | Chatbot
        |--------------------------------------------------------------------------
        |
        | This is now a concrete module, so it must be registered here before
        | admin-fallback.php is loaded.
        |
        */

        Route::get(
            '/chatbot',
            [ChatbotController::class, 'index']
        )->name('chatbot.index');


        Route::post(
            '/chatbot/ask',
            [ChatbotController::class, 'ask']
        )
            ->middleware('throttle:30,1')
            ->name('chatbot.ask');
    });


/*
|--------------------------------------------------------------------------
| Extended / Fallback Admin Modules
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Keep this require OUTSIDE the admin group above.
|
| admin-fallback.php already applies:
|
|     ->middleware(['auth'])
|     ->prefix('admin')
|
| Concrete modules already defined above will not be replaced.
|
*/

require __DIR__ . '/admin-fallback.php';
