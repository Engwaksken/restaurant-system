<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PermissionManagementController extends Controller
{
    public const ABILITIES = [
        'admin.dashboard.view' => 'View dashboard',
        'admin.menu-items.view' => 'View menu items',
        'admin.menu-items.create' => 'Create menu items',
        'admin.menu-items.update' => 'Update menu items',
        'admin.menu-items.delete' => 'Delete menu items',
        'menu.categories.view' => 'View menu categories',
        'menu.categories.manage' => 'Manage menu categories',
        'menu.recipes.view' => 'View recipes',
        'menu.recipes.manage' => 'Manage recipes',
        'menu.availability.manage' => 'Mark menu items available or unavailable',
        'admin.pos.manage' => 'Operate point of sale',
        'orders.view' => 'View orders',
        'orders.manage' => 'Manage order status',
        'orders.payments.create' => 'Record order payments',
        'orders.refunds.view' => 'View refunds',
        'orders.refunds.create' => 'Issue refunds against payments',
        'finance.cash-register.use' => 'Open, count and close own cash drawer',
        'finance.cash-register.manage' => 'Oversee all cash drawers in scope',
        'finance.cash-register.review' => 'Review cash count variances',
        'finance.reconciliation.view' => 'View payment reconciliation',
        'finance.reconciliation.manage' => 'Match and dispute non-cash payments',
        'kitchen.view' => 'View kitchen display',
        'kitchen.manage' => 'Start and mark kitchen orders ready',
        'tables.view' => 'View floors and tables',
        'tables.manage' => 'Manage floors, tables and table status',
        'reservations.view' => 'View reservations',
        'reservations.manage' => 'Create and manage reservations',
        'customers.view' => 'View customers and their history',
        'customers.manage' => 'Create and edit customers',
        'customers.feedback.view' => 'View customer feedback',
        'customers.loyalty.view' => 'View loyalty balances and ledger',
        'customers.loyalty.adjust' => 'Manually adjust loyalty points',
        'customers.erase' => 'Erase customer personal data',
        'menu.options.manage' => 'Manage menu variants and add-ons',
        'admin.pos.discount' => 'Apply manual discounts at the POS',
        'reports.sales.export' => 'Export sales reports',
        'inventory.view' => 'View inventory',
        'inventory.manage' => 'Manage inventory items',
        'inventory.adjust' => 'Adjust stock',
        'inventory.transfers.view' => 'View branch stock transfers',
        'inventory.transfers.manage' => 'Transfer stock between branches',
        'inventory.wastage.view' => 'View inventory wastage',
        'inventory.wastage.manage' => 'Record inventory wastage',
        'finance.expenses.view' => 'View expenses',
        'finance.expenses.create' => 'Record expenses',
        'finance.expenses.manage' => 'Manage expense categories',
        'purchasing.suppliers.view' => 'View suppliers',
        'purchasing.suppliers.manage' => 'Manage suppliers',
        'purchasing.purchases.view' => 'View received purchases',
        'purchasing.purchases.receive' => 'Receive stock purchases',
        'purchasing.purchase-orders.view' => 'View purchase orders',
        'purchasing.purchase-orders.create' => 'Create purchase orders',
        'purchasing.purchase-orders.cancel' => 'Cancel unreceived purchase orders',
        'purchasing.supplier-payments.create' => 'Record supplier payments',
        'settings.roles-permissions.manage' => 'Manage staff permissions',
        'settings.activity-logs.view' => 'View the restaurant activity log',
        'settings.branches.manage' => 'Manage branches (restaurant-wide staff only)',
        'settings.payment-gateways.manage' => 'Configure online payment gateways (restaurant-wide staff only)',
        'settings.branding.manage' => 'Manage restaurant branding, logo, favicon and email styling',
        'employees.staff.manage' => 'Create, edit and deactivate staff accounts',
        'employees.attendance.manage' => 'View, record and correct staff attendance',
        'employees.shifts.manage' => 'Plan staff shifts',
        'employees.payroll.view' => 'View payroll runs and payslips',
        'employees.payroll.manage' => 'Set pay rates and prepare payroll',
        'employees.payroll.approve' => 'Approve or void payroll',
        'employees.payroll.pay' => 'Mark approved payroll as paid',
        'chatbot.view' => 'View tenant chatbot conversations',
    ];

    public function index(Request $request): View
    {
        $restaurantId = $this->authorizeManager($request);
        $users = User::query()
            ->where('restaurant_id', $restaurantId)
            ->with(['permissions', 'branch:id,name'])
            ->orderBy('name')
            ->paginate(25);

        $grants = DB::table('user_permissions')
            ->whereIn('user_id', User::query()->where('restaurant_id', $restaurantId)->select('id'));
        $pageStats = [
            ['label' => 'Staff accounts', 'value' => number_format($users->total()), 'icon' => 'fas fa-users', 'tone' => 'brand'],
            ['label' => 'Full administrators', 'value' => number_format((clone $grants)->where('ability', 'admin')->distinct()->count('user_id')), 'icon' => 'fas fa-user-shield', 'tone' => 'danger', 'hint' => 'Managed from the command line'],
            ['label' => 'Without permissions', 'value' => number_format(User::query()->where('restaurant_id', $restaurantId)->whereDoesntHave('permissions')->count()), 'icon' => 'fas fa-user-lock', 'tone' => 'warning', 'hint' => 'Cannot use any module yet'],
            ['label' => 'Available permissions', 'value' => number_format(count(self::ABILITIES)), 'icon' => 'fas fa-key', 'tone' => 'info'],
        ];

        return view('admin.permissions.index', [
            'pageStats' => $pageStats,
            'users' => $users,
            'abilities' => self::ABILITIES,
            'roles' => \App\Support\StaffRoles::all(),
        ]);
    }

    public function update(Request $request, User $staff, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeManager($request);
        abort_unless((int) $staff->restaurant_id === $restaurantId, 404);

        $validated = $request->validate([
            'abilities' => ['nullable', 'array', 'max:' . count(self::ABILITIES)],
            'abilities.*' => ['required', 'string', 'distinct', 'in:' . implode(',', array_keys(self::ABILITIES))],
        ]);

        $hasWildcard = DB::table('user_permissions')
            ->where('user_id', $staff->id)
            ->where('ability', 'admin')
            ->exists();
        abort_if($hasWildcard, 422, 'Wildcard administrators must be managed through reviewed CLI provisioning.');

        $abilities = $validated['abilities'] ?? [];
        DB::transaction(function () use ($staff, $abilities): void {
            DB::table('user_permissions')->where('user_id', $staff->id)
                ->whereIn('ability', array_keys(self::ABILITIES))
                ->delete();

            if ($abilities !== []) {
                $now = now();
                DB::table('user_permissions')->insert(array_map(
                    fn (string $ability): array => [
                        'user_id' => $staff->id,
                        'ability' => $ability,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $abilities
                ));
            }
        });

        $logger->log('staff.permissions.updated', $staff, [
            'abilities' => $abilities,
            'restaurant_id' => $restaurantId,
        ]);

        return back()->with('success', 'Staff permissions updated.');
    }

    private function authorizeManager(Request $request): int
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless(
            $user->can('settings.roles-permissions.manage') || $user->can('admin'),
            403
        );

        return (int) $user->restaurant_id;
    }
}
