<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Staff roles are named bundles of the explicit per-user permissions in
 * PermissionManagementController::ABILITIES. Assigning a role grants exactly
 * its permissions; staff whose grants match no role are shown as "Custom".
 */
final class StaffRoles
{
    public const ROLES = [
        'manager' => [
            'label' => 'Manager',
            'icon' => 'fas fa-user-tie',
            'tone' => 'brand',
            'description' => 'Runs day-to-day operations: orders, menu, tables, customers, stock, expenses and staff rotas.',
            'abilities' => [
                'admin.dashboard.view', 'admin.menu-items.view', 'admin.menu-items.create', 'admin.menu-items.update',
                'menu.categories.view', 'menu.categories.manage', 'menu.recipes.view', 'menu.recipes.manage',
                'menu.availability.manage', 'menu.options.manage', 'admin.pos.manage', 'admin.pos.discount',
                'orders.view', 'orders.manage', 'orders.payments.create', 'orders.refunds.view', 'orders.refunds.create',
                'finance.cash-register.use', 'finance.cash-register.manage', 'finance.cash-register.review',
                'kitchen.view', 'kitchen.manage', 'tables.view', 'tables.manage', 'reservations.view', 'reservations.manage',
                'customers.view', 'customers.manage', 'customers.feedback.view', 'customers.loyalty.view',
                'reports.sales.export', 'inventory.view', 'inventory.manage', 'inventory.adjust',
                'inventory.transfers.view', 'inventory.wastage.view', 'inventory.wastage.manage',
                'finance.expenses.view', 'finance.expenses.create', 'purchasing.suppliers.view',
                'purchasing.purchases.view', 'purchasing.purchase-orders.view', 'purchasing.purchase-orders.create',
                'employees.attendance.manage', 'employees.shifts.manage', 'settings.activity-logs.view',
            ],
        ],
        'cashier' => [
            'label' => 'Cashier',
            'icon' => 'fas fa-cash-register',
            'tone' => 'success',
            'description' => 'Takes orders and payments at the point of sale and runs their own cash drawer.',
            'abilities' => [
                'admin.pos.manage', 'orders.view', 'orders.payments.create', 'finance.cash-register.use',
                'customers.view', 'tables.view', 'reservations.view',
            ],
        ],
        'waiter' => [
            'label' => 'Waiter',
            'icon' => 'fas fa-bell-concierge',
            'tone' => 'info',
            'description' => 'Seats guests, takes table orders and keeps table status up to date.',
            'abilities' => [
                'admin.pos.manage', 'orders.view', 'tables.view', 'tables.manage', 'reservations.view',
                'reservations.manage', 'customers.view', 'kitchen.view',
            ],
        ],
        'kitchen' => [
            'label' => 'Kitchen staff',
            'icon' => 'fas fa-fire-burner',
            'tone' => 'warning',
            'description' => 'Works the kitchen display, marks dishes ready and updates menu availability.',
            'abilities' => [
                'kitchen.view', 'kitchen.manage', 'menu.availability.manage', 'menu.recipes.view',
                'inventory.view', 'inventory.wastage.view', 'inventory.wastage.manage',
            ],
        ],
        'storekeeper' => [
            'label' => 'Storekeeper',
            'icon' => 'fas fa-boxes-stacked',
            'tone' => 'violet',
            'description' => 'Manages stock, transfers, wastage and receiving deliveries from suppliers.',
            'abilities' => [
                'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfers.view',
                'inventory.transfers.manage', 'inventory.wastage.view', 'inventory.wastage.manage',
                'purchasing.suppliers.view', 'purchasing.purchases.view', 'purchasing.purchases.receive',
                'purchasing.purchase-orders.view', 'menu.recipes.view',
            ],
        ],
        'accountant' => [
            'label' => 'Accountant',
            'icon' => 'fas fa-calculator',
            'tone' => 'neutral',
            'description' => 'Handles finance: reconciliation, expenses, supplier payments, payroll and reports.',
            'abilities' => [
                'admin.dashboard.view', 'orders.view', 'orders.refunds.view', 'finance.cash-register.review',
                'finance.reconciliation.view', 'finance.reconciliation.manage', 'finance.expenses.view',
                'finance.expenses.create', 'finance.expenses.manage', 'purchasing.suppliers.view',
                'purchasing.purchases.view', 'purchasing.supplier-payments.create', 'reports.sales.export',
                'employees.payroll.view', 'employees.payroll.manage',
            ],
        ],
        'hr' => [
            'label' => 'HR officer',
            'icon' => 'fas fa-id-badge',
            'tone' => 'danger',
            'description' => 'Manages staff accounts, attendance, shifts and pay rates.',
            'abilities' => [
                'employees.staff.manage', 'employees.attendance.manage', 'employees.shifts.manage',
                'employees.payroll.view', 'employees.payroll.manage',
            ],
        ],
    ];

    /** @return Collection<string, array> */
    public static function all(): Collection
    {
        return collect(self::ROLES);
    }

    public static function exists(string $key): bool
    {
        return isset(self::ROLES[$key]);
    }

    /** @return list<string> */
    public static function abilities(string $key): array
    {
        return array_values(array_unique(self::ROLES[$key]['abilities'] ?? []));
    }

    /**
     * The role whose permissions exactly match these grants, or null for a
     * custom set. Wildcard administrators are reported separately.
     */
    public static function match(array $granted): ?string
    {
        $granted = array_values(array_unique($granted));
        sort($granted);
        foreach (self::ROLES as $key => $role) {
            $abilities = self::abilities($key);
            sort($abilities);
            if ($abilities === $granted) {
                return $key;
            }
        }

        return null;
    }
}
