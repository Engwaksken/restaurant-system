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

    // Kitchen
    ['admin.kitchen.index', 'kitchen', 'kitchen.index'],
    ['admin.kitchen.pending', 'kitchen/pending', 'kitchen.pending'],
    ['admin.kitchen.preparing', 'kitchen/preparing', 'kitchen.preparing'],
    ['admin.kitchen.ready', 'kitchen/ready', 'kitchen.ready'],
    ['admin.kitchen.completed', 'kitchen/completed', 'kitchen.completed'],

    // Menu
    ['admin.menu-categories.index', 'menu-categories', 'menu.categories'],
    ['admin.menu-addons.index', 'menu-addons', 'menu.addons'],
    ['admin.menu-variants.index', 'menu-variants', 'menu.variants'],
    ['admin.recipes.index', 'recipes', 'menu.recipes'],
    ['admin.menu-availability.index', 'menu-availability', 'menu.availability'],

    // Tables
    ['admin.floors.index', 'floors', 'tables.floors'],
    ['admin.tables.index', 'tables', 'tables.tables'],
    ['admin.reservations.index', 'reservations', 'tables.reservations'],
    ['admin.qr-ordering.index', 'qr-ordering', 'tables.qr'],

    // Customers
    ['admin.customers.index', 'customers', 'customers.customers'],
    ['admin.loyalty.index', 'loyalty', 'customers.loyalty'],
    ['admin.feedback.index', 'feedback', 'customers.feedback'],
    ['admin.customer-accounts.index', 'customer-accounts', 'customers.accounts'],

    // Inventory
    ['admin.inventory.products', 'inventory/products', 'inventory.products'],
    ['admin.inventory.categories', 'inventory/categories', 'inventory.categories'],
    ['admin.inventory.stock', 'inventory/stock', 'inventory.stock'],
    ['admin.inventory.adjustments', 'inventory/adjustments', 'inventory.adjustments'],
    ['admin.inventory.transfers', 'inventory/transfers', 'inventory.transfers'],
    ['admin.inventory.wastage', 'inventory/wastage', 'inventory.wastage'],
    ['admin.inventory.low-stock', 'inventory/low-stock', 'inventory.low-stock'],

    // Purchasing
    ['admin.suppliers.index', 'suppliers', 'purchasing.suppliers'],
    ['admin.purchase-orders.index', 'purchase-orders', 'purchasing.purchase-orders'],
    ['admin.purchases.index', 'purchases', 'purchasing.purchases'],
    ['admin.supplier-payments.index', 'supplier-payments', 'purchasing.supplier-payments'],
    ['admin.payables.index', 'payables', 'purchasing.payables'],

    // Finance
    ['admin.payments.index', 'payments', 'finance.payments'],
    ['admin.expenses.index', 'expenses', 'finance.expenses'],
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
    ['admin.settings.roles-permissions', 'settings/roles-permissions', 'settings.roles-permissions'],
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
