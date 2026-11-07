<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminModuleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Restaurant Admin Fallback Routes
|--------------------------------------------------------------------------
|
| Load this file AFTER your concrete admin routes in routes/web.php:
|
|     require __DIR__ . '/admin-fallback.php';
|
| Do not include this file inside another ->prefix('admin') group.
|
*/

$adminModuleRoutes = [
    // POS

    // Menu

    // Tables
    ['admin.qr-ordering.index', 'qr-ordering', 'tables.qr'],

    // Customers
    ['admin.customer-accounts.index', 'customer-accounts', 'customers.accounts'],

    // Inventory

    // Purchasing
    ['admin.payables.index', 'payables', 'purchasing.payables'],

    // Finance
    ['admin.payments.index', 'payments', 'finance.payments'],
    ['admin.receivables.index', 'receivables', 'finance.receivables'],
    ['admin.accounts.index', 'accounts', 'finance.accounts'],
    ['admin.general-ledger.index', 'general-ledger', 'finance.general-ledger'],

    // Employees
    ['admin.roles.index', 'roles', 'employees.roles'],

    // Direct modules
    ['admin.reports.index', 'reports', 'reports.index'],
    ['admin.notifications.index', 'notifications', 'notifications.index'],
  

    // Settings
    ['admin.settings.taxes', 'settings/taxes', 'settings.taxes'],
    ['admin.settings.payment-gateways', 'settings/payment-gateways', 'settings.payment-gateways'],
    ['admin.settings.receipt', 'settings/receipt', 'settings.receipt'],
    ['admin.settings.invoice', 'settings/invoice', 'settings.invoice'],
    ['admin.settings.email', 'settings/email', 'settings.email'],
    ['admin.settings.sms', 'settings/sms', 'settings.sms'],
    ['admin.settings.ai', 'settings/ai', 'settings.ai'],
    ['admin.settings.accessibility', 'settings/accessibility', 'settings.accessibility'],
    ['admin.settings.backups', 'settings/backups', 'settings.backups'],
];

Route::middleware(['auth'])
    ->prefix('admin')
    ->group(function () use ($adminModuleRoutes): void {
        if (!Route::has('admin.modules.store')) {
            Route::post('/modules/{module}', [AdminModuleController::class, 'store'])
                ->where('module', '[A-Za-z0-9._-]+')
                ->name('admin.modules.store');
        }

        if (!Route::has('admin.modules.update')) {
            Route::put('/modules/{module}/{id}', [AdminModuleController::class, 'update'])
                ->where('module', '[A-Za-z0-9._-]+')
                ->whereNumber('id')
                ->name('admin.modules.update');
        }

        if (!Route::has('admin.modules.destroy')) {
            Route::delete('/modules/{module}/{id}', [AdminModuleController::class, 'destroy'])
                ->where('module', '[A-Za-z0-9._-]+')
                ->whereNumber('id')
                ->name('admin.modules.destroy');
        }

        foreach ($adminModuleRoutes as [$routeName, $uri, $module]) {
            if (Route::has($routeName)) {
                continue;
            }

            Route::get('/' . ltrim($uri, '/'), [AdminModuleController::class, 'show'])
                ->defaults('module', $module)
                ->name($routeName);
        }
    });
