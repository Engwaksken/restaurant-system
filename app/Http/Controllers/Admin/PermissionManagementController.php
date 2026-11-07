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
    private const ABILITIES = [
        'admin.dashboard.view' => 'View dashboard',
        'admin.menu-items.view' => 'View menu items',
        'admin.menu-items.create' => 'Create menu items',
        'admin.menu-items.update' => 'Update menu items',
        'admin.menu-items.delete' => 'Delete menu items',
        'menu.categories.view' => 'View menu categories',
        'menu.categories.manage' => 'Manage menu categories',
        'menu.recipes.view' => 'View recipes',
        'menu.recipes.manage' => 'Manage recipes',
        'admin.pos.manage' => 'Operate point of sale',
        'orders.view' => 'View orders',
        'orders.manage' => 'Manage order status',
        'orders.payments.create' => 'Record order payments',
        'kitchen.view' => 'View kitchen display',
        'kitchen.manage' => 'Start and mark kitchen orders ready',
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
        'chatbot.view' => 'View tenant chatbot conversations',
    ];

    public function index(Request $request): View
    {
        $restaurantId = $this->authorizeManager($request);
        $users = User::query()
            ->where('restaurant_id', $restaurantId)
            ->with('permissions')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.permissions.index', [
            'users' => $users,
            'abilities' => self::ABILITIES,
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
