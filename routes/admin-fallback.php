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
    ['admin.pos.held', 'pos/held', 'pos.held'],
    ['admin.pos.current', 'pos/current', 'pos.current'],
    ['admin.pos.history', 'pos/history', 'pos.history'],
    ['admin.pos.returns', 'pos/returns', 'pos.returns'],

    // Menu
    ['admin.menu-addons.index', 'menu-addons', 'menu.addons'],
    ['admin.menu-variants.index', 'menu-variants', 'menu.variants'],
    ['admin.menu-availability.index', 'menu-availability', 'menu.availability'],

    // Tables
    ['admin.qr-ordering.index', 'qr-ordering', 'tables.qr'],

    // Customers
    ['admin.loyalty.index', 'loyalty', 'customers.loyalty'],
    ['admin.customer-accounts.index', 'customer-accounts', 'customers.accounts'],

    // Inventory

    // Purchasing
    ['admin.payables.index', 'payables', 'purchasing.payables'],

    // Finance
    ['admin.payments.index', 'payments', 'finance.payments'],
    ['admin.receivables.index', 'receivables', 'finance.receivables'],
    ['admin.cash-register.index', 'cash-register', 'finance.cash-register'],
    ['admin.accounts.index', 'accounts', 'finance.accounts'],
    ['admin.general-ledger.index', 'general-ledger', 'finance.general-ledger'],
    ['admin.reconciliation.index', 'reconciliation', 'finance.reconciliation'],

    // Employees
    ['admin.staff.index', 'staff', 'employees.staff'],
    ['admin.roles.index', 'roles', 'employees.roles'],
    ['admin.attendance.index', 'attendance', 'employees.attendance'],
    ['admin.shifts.index', 'shifts', 'employees.shifts'],
    ['admin.payroll.index', 'payroll', 'employees.payroll'],

    // Direct modules
    ['admin.reports.index', 'reports', 'reports.index'],
    ['admin.notifications.index', 'notifications', 'notifications.index'],
  

    // Settings
    ['admin.settings.restaurant', 'settings/restaurant', 'settings.restaurant'],
    ['admin.settings.branches', 'settings/branches', 'settings.branches'],
    ['admin.settings.taxes', 'settings/taxes', 'settings.taxes'],
    ['admin.settings.payment-gateways', 'settings/payment-gateways', 'settings.payment-gateways'],
    ['admin.settings.receipt', 'settings/receipt', 'settings.receipt'],
    ['admin.settings.invoice', 'settings/invoice', 'settings.invoice'],
    ['admin.settings.email', 'settings/email', 'settings.email'],
    ['admin.settings.sms', 'settings/sms', 'settings.sms'],
    ['admin.settings.ai', 'settings/ai', 'settings.ai'],
    ['admin.settings.accessibility', 'settings/accessibility', 'settings.accessibility'],
    ['admin.settings.backups', 'settings/backups', 'settings.backups'],
    ['admin.settings.activity-logs', 'settings/activity-logs', 'settings.activity-logs'],
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
