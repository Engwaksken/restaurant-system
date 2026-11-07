<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\CashRegisterController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\WorkforceController;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\ChatbotController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FeedbackController;
use App\Http\Controllers\Admin\MenuAvailabilityController;
use App\Http\Controllers\Admin\LoyaltyController;
use App\Http\Controllers\Admin\MenuOptionController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\MenuCategoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\KitchenController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PermissionManagementController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\PurchasingController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\WastageController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PublicOrderController;
use App\Http\Controllers\Public\PublicOrderFeedbackController;
use App\Http\Controllers\Public\PublicOrderPaymentController;
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

        Route::post('/feedback/{order}', [PublicOrderFeedbackController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('feedback');

        Route::post('/pay/{order}', [PublicOrderPaymentController::class, 'pay'])
            ->middleware('throttle:5,1')
            ->name('pay');

        Route::get('/payment-return/{order}/{payment}', [PublicOrderPaymentController::class, 'complete'])
            ->whereNumber('payment')
            ->middleware('throttle:30,1')
            ->name('payment.return');
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

        Route::get('/menu-categories', [MenuCategoryController::class, 'index'])->name('menu-categories.index');
        Route::post('/menu-categories', [MenuCategoryController::class, 'store'])->name('menu-categories.store');
        Route::put('/menu-categories/{category}', [MenuCategoryController::class, 'update'])
            ->whereNumber('category')
            ->name('menu-categories.update');
        Route::delete('/menu-categories/{category}', [MenuCategoryController::class, 'destroy'])
            ->whereNumber('category')
            ->name('menu-categories.destroy');


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

        Route::get('/kitchen', [KitchenController::class, 'index'])->name('kitchen.index');
        Route::get('/kitchen/pending', [KitchenController::class, 'pending'])->name('kitchen.pending');
        Route::get('/kitchen/preparing', [KitchenController::class, 'preparing'])->name('kitchen.preparing');
        Route::get('/kitchen/ready', [KitchenController::class, 'ready'])->name('kitchen.ready');
        Route::get('/kitchen/completed', [KitchenController::class, 'completed'])->name('kitchen.completed');
        Route::post('/kitchen/orders/{order}/advance', [KitchenController::class, 'advance'])
            ->whereNumber('order')
            ->middleware('throttle:120,1')
            ->name('kitchen.advance');

        Route::get('/floors', [TableController::class, 'floors'])->name('floors.index');
        Route::post('/floors', [TableController::class, 'storeFloor'])->name('floors.store');
        Route::put('/floors/{floor}', [TableController::class, 'updateFloor'])->whereNumber('floor')->name('floors.update');
        Route::delete('/floors/{floor}', [TableController::class, 'destroyFloor'])->whereNumber('floor')->name('floors.destroy');
        Route::get('/tables', [TableController::class, 'tables'])->name('tables.index');
        Route::post('/tables', [TableController::class, 'storeTable'])->name('tables.store');
        Route::put('/tables/{table}', [TableController::class, 'updateTable'])->whereNumber('table')->name('tables.update');
        Route::patch('/tables/{table}/status', [TableController::class, 'updateTableStatus'])->whereNumber('table')->name('tables.status');
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])
            ->whereNumber('reservation')->name('reservations.update');
        Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])
            ->whereNumber('reservation')->name('reservations.status');

        Route::post('/pos/hold', [PosController::class, 'hold'])->name('pos.hold');
        Route::get('/pos/held', [PosController::class, 'held'])->name('pos.held');
        Route::delete('/pos/held/{held}', [PosController::class, 'discardHeld'])->whereNumber('held')->name('pos.held.destroy');
        Route::get('/pos/current', [OrderController::class, 'current'])->name('pos.current');
        Route::get('/pos/history', [OrderController::class, 'history'])->name('pos.history');
        Route::get('/menu-{type}', [MenuOptionController::class, 'index'])->whereIn('type', ['variants', 'addons'])->name('menu-options.index');
        Route::post('/menu-{type}', [MenuOptionController::class, 'store'])->whereIn('type', ['variants', 'addons'])->name('menu-options.store');
        Route::put('/menu-{type}/{option}', [MenuOptionController::class, 'update'])->whereIn('type', ['variants', 'addons'])->whereNumber('option')->name('menu-options.update');
        Route::delete('/menu-{type}/{option}', [MenuOptionController::class, 'destroy'])->whereIn('type', ['variants', 'addons'])->whereNumber('option')->name('menu-options.destroy');
        Route::get('/menu-availability', [MenuAvailabilityController::class, 'index'])->name('menu-availability.index');
        Route::patch('/menu-availability/{item}', [MenuAvailabilityController::class, 'update'])
            ->whereNumber('item')->name('menu-availability.update');
        Route::get('/settings/activity-logs', [ActivityLogController::class, 'index'])->name('settings.activity-logs');

        Route::get('/settings/restaurant', [BrandingController::class, 'edit'])->name('settings.restaurant');
        Route::put('/settings/restaurant', [BrandingController::class, 'update'])->name('settings.restaurant.update');

        Route::get('/settings/payment-gateways', [PaymentGatewayController::class, 'edit'])->name('settings.payment-gateways');
        Route::put('/settings/payment-gateways/{gateway}', [PaymentGatewayController::class, 'update'])
            ->where('gateway', '[a-z]+')
            ->name('settings.payment-gateways.update');

        Route::get('/settings/branches', [BranchController::class, 'index'])->name('settings.branches');
        Route::post('/settings/branches', [BranchController::class, 'store'])->name('settings.branches.store');
        Route::put('/settings/branches/{branch}', [BranchController::class, 'update'])->whereNumber('branch')->name('settings.branches.update');
        Route::patch('/settings/branches/{branch}/status', [BranchController::class, 'updateStatus'])->whereNumber('branch')->name('settings.branches.status');
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->whereNumber('staff')->name('staff.update');
        Route::patch('/staff/{staff}/status', [StaffController::class, 'updateStatus'])->whereNumber('staff')->name('staff.status');
        Route::put('/staff/{staff}/password', [StaffController::class, 'resetPassword'])->whereNumber('staff')->name('staff.password');

        Route::get('/cash-register', [CashRegisterController::class, 'index'])->name('cash-register.index');
        Route::post('/cash-register', [CashRegisterController::class, 'open'])->name('cash-register.open');
        Route::post('/cash-register/{session}/movements', [CashRegisterController::class, 'movement'])->whereNumber('session')->name('cash-register.movement');
        Route::post('/cash-register/{session}/close', [CashRegisterController::class, 'close'])->whereNumber('session')->name('cash-register.close');
        Route::post('/cash-register/{session}/review', [CashRegisterController::class, 'review'])->whereNumber('session')->name('cash-register.review');
        Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::post('/reconciliation/match', [ReconciliationController::class, 'match'])->name('reconciliation.match');
        Route::patch('/reconciliation/payments/{payment}', [ReconciliationController::class, 'dispute'])->whereNumber('payment')->name('reconciliation.dispute');
        Route::get('/pos/returns', [RefundController::class, 'index'])->name('pos.returns');
        Route::post('/orders/{order}/refunds', [RefundController::class, 'store'])->whereNumber('order')->name('orders.refunds.store');

        Route::get('/attendance', [WorkforceController::class, 'attendance'])->name('attendance.index');
        Route::post('/attendance/clock', [WorkforceController::class, 'clock'])->middleware('throttle:20,1')->name('attendance.clock');
        Route::post('/attendance', [WorkforceController::class, 'storeAttendance'])->name('attendance.store');
        Route::put('/attendance/{attendance}', [WorkforceController::class, 'updateAttendance'])->whereNumber('attendance')->name('attendance.update');
        Route::get('/shifts', [WorkforceController::class, 'shifts'])->name('shifts.index');
        Route::post('/shifts', [WorkforceController::class, 'storeShift'])->name('shifts.store');
        Route::delete('/shifts/{shift}', [WorkforceController::class, 'destroyShift'])->whereNumber('shift')->name('shifts.destroy');
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/payroll', [PayrollController::class, 'store'])->name('payroll.store');
        Route::post('/payroll/compensation', [PayrollController::class, 'saveCompensation'])->name('payroll.compensation');
        Route::get('/payroll/{run}', [PayrollController::class, 'show'])->whereNumber('run')->name('payroll.show');
        Route::get('/payroll/{run}/payslips/{line}', [PayrollController::class, 'payslip'])->whereNumber(['run', 'line'])->name('payroll.payslip');
        Route::post('/payroll/{run}/recalculate', [PayrollController::class, 'recalculate'])->whereNumber('run')->name('payroll.recalculate');
        Route::post('/payroll/{run}/lines/{line}/adjustments', [PayrollController::class, 'addAdjustment'])->whereNumber(['run', 'line'])->name('payroll.adjustments.store');
        Route::delete('/payroll/{run}/adjustments/{adjustment}', [PayrollController::class, 'removeAdjustment'])->whereNumber(['run', 'adjustment'])->name('payroll.adjustments.destroy');
        Route::post('/payroll/{run}/approve', [PayrollController::class, 'approve'])->whereNumber('run')->name('payroll.approve');
        Route::post('/payroll/{run}/pay', [PayrollController::class, 'pay'])->whereNumber('run')->name('payroll.pay');
        Route::post('/payroll/{run}/void', [PayrollController::class, 'void'])->whereNumber('run')->name('payroll.void');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'erase'])->whereNumber('customer')->name('customers.erase');
        Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
        Route::get('/loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');
        Route::post('/loyalty/adjust', [LoyaltyController::class, 'adjust'])->name('loyalty.adjust');

        Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
        Route::post('/recipes', [RecipeController::class, 'store'])->name('recipes.store');
        Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy'])
            ->whereNumber('recipe')
            ->name('recipes.destroy');

        Route::get('/inventory/products', [InventoryController::class, 'index'])->name('inventory.products');
        Route::get('/inventory/categories', [InventoryController::class, 'index'])->name('inventory.categories');
        Route::get('/inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock');
        Route::get('/inventory/adjustments', [InventoryController::class, 'adjustments'])->name('inventory.adjustments');
        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');
        Route::get('/inventory/transfers', [StockTransferController::class, 'index'])->name('inventory.transfers');
        Route::post('/inventory/transfers', [StockTransferController::class, 'store'])->name('inventory.transfers.store');
        Route::get('/inventory/wastage', [WastageController::class, 'index'])->name('inventory.wastage');
        Route::post('/inventory/wastage', [WastageController::class, 'store'])->name('inventory.wastage.store');
        Route::post('/inventory/products', [InventoryController::class, 'storeItem'])->name('inventory.products.store');
        Route::put('/inventory/products/{item}', [InventoryController::class, 'updateItem'])
            ->whereNumber('item')->name('inventory.products.update');
        Route::delete('/inventory/products/{item}', [InventoryController::class, 'destroyItem'])
            ->whereNumber('item')->name('inventory.products.destroy');
        Route::post('/inventory/categories', [InventoryController::class, 'storeCategory'])->name('inventory.categories.store');
        Route::post('/inventory/stock-adjustments', [InventoryController::class, 'adjustStock'])->name('inventory.stock.adjust');

        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])
            ->whereNumber('expense')->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])
            ->whereNumber('expense')->name('expenses.destroy');
        Route::post('/expense-categories', [ExpenseController::class, 'storeCategory'])->name('expense-categories.store');
        Route::put('/expense-categories/{category}', [ExpenseController::class, 'updateCategory'])
            ->whereNumber('category')->name('expense-categories.update');
        Route::delete('/expense-categories/{category}', [ExpenseController::class, 'destroyCategory'])
            ->whereNumber('category')->name('expense-categories.destroy');

        Route::get('/suppliers', [PurchasingController::class, 'suppliers'])->name('suppliers.index');
        Route::post('/suppliers', [PurchasingController::class, 'storeSupplier'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [PurchasingController::class, 'updateSupplier'])
            ->whereNumber('supplier')->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [PurchasingController::class, 'destroySupplier'])
            ->whereNumber('supplier')->name('suppliers.destroy');
        Route::get('/purchases', [PurchasingController::class, 'purchases'])->name('purchases.index');
        Route::post('/purchases/receive', [PurchasingController::class, 'receive'])->name('purchases.receive');
        Route::get('/purchase-orders', [PurchasingController::class, 'purchaseOrders'])->name('purchase-orders.index');
        Route::post('/purchase-orders', [PurchasingController::class, 'storePurchaseOrder'])->name('purchase-orders.store');
        Route::patch('/purchase-orders/{purchaseOrder}/cancel', [PurchasingController::class, 'cancelPurchaseOrder'])
            ->whereNumber('purchaseOrder')->name('purchase-orders.cancel');
        Route::get('/purchase-orders/{purchaseOrder}/receive', [PurchasingController::class, 'receivePurchaseOrderForm'])
            ->whereNumber('purchaseOrder')->name('purchase-orders.receive');
        Route::post('/purchases/{purchase}/payments', [PurchasingController::class, 'recordSupplierPayment'])
            ->whereNumber('purchase')->name('purchases.payments.store');
        Route::get('/supplier-payments', [PurchasingController::class, 'purchases'])->name('supplier-payments.index');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles/{role}/assign', [RoleController::class, 'assign'])
            ->where('role', '[a-z_-]+')
            ->name('roles.assign');

        Route::get('/settings/roles-permissions', [PermissionManagementController::class, 'index'])
            ->name('settings.roles-permissions');
        Route::put('/settings/roles-permissions/{staff}', [PermissionManagementController::class, 'update'])
            ->whereNumber('staff')
            ->name('settings.roles-permissions.update');


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

        Route::get('/chatbot/{conversation}', [ChatbotController::class, 'show'])
            ->whereNumber('conversation')
            ->name('chatbot.show');


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
