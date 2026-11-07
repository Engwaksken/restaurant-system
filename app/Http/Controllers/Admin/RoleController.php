<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\StaffRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Staff roles: named permission bundles. Assigning a role replaces the staff
 * member's managed permissions with the role's set, exactly like saving the
 * same boxes on Roles & Permissions.
 */
class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $restaurantId = $this->authorizeRoles($request);
        $abilities = PermissionManagementController::ABILITIES;

        $team = User::query()
            ->where('restaurant_id', $restaurantId)
            ->with(['permissions:id,user_id,ability', 'branch:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'branch_id', 'is_active']);

        $assignments = $team->mapWithKeys(function (User $user): array {
            $granted = $user->permissions->pluck('ability')->all();
            $role = in_array('admin', $granted, true) ? 'admin' : (count($granted) === 0 ? 'none' : (StaffRoles::match($granted) ?? 'custom'));

            return [$user->id => $role];
        });
        $counts = $assignments->countBy();

        $members = $team->forPage(max(1, (int) $request->query('page', 1)), 25);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $members->values(), $team->count(), 25, max(1, (int) $request->query('page', 1)),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $pageStats = [
            ['label' => 'Roles available', 'value' => number_format(StaffRoles::all()->count()), 'icon' => 'fas fa-user-shield', 'tone' => 'brand'],
            ['label' => 'Staff with a role', 'value' => number_format($assignments->filter(fn ($role) => StaffRoles::exists($role))->count()), 'icon' => 'fas fa-user-check', 'tone' => 'success'],
            ['label' => 'Custom permissions', 'value' => number_format($counts->get('custom', 0)), 'icon' => 'fas fa-sliders', 'tone' => 'info', 'hint' => 'Hand-picked, no role'],
            ['label' => 'No access yet', 'value' => number_format($counts->get('none', 0)), 'icon' => 'fas fa-user-lock', 'tone' => $counts->get('none', 0) ? 'warning' : 'neutral', 'hint' => 'Assign a role to get started'],
        ];

        return view('admin.roles.index', [
            'pageStats' => $pageStats,
            'roles' => StaffRoles::all(),
            'abilities' => $abilities,
            'counts' => $counts,
            'assignments' => $assignments,
            'members' => $paginator,
            'assignable' => $team->filter(fn (User $user) => $assignments[$user->id] !== 'admin' && $user->is_active !== false)->values(),
        ]);
    }

    public function assign(Request $request, string $role, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeRoles($request);
        abort_unless(StaffRoles::exists($role), 404);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('restaurant_id', $restaurantId)],
        ], ['user_id.required' => 'Choose a staff member to assign this role to.']);

        $staff = User::query()->where('restaurant_id', $restaurantId)->findOrFail($data['user_id']);
        abort_if(
            DB::table('user_permissions')->where('user_id', $staff->id)->where('ability', 'admin')->exists(),
            422,
            'Wildcard administrators must be managed through reviewed CLI provisioning.'
        );

        $abilities = StaffRoles::abilities($role);
        DB::transaction(function () use ($staff, $abilities): void {
            DB::table('user_permissions')->where('user_id', $staff->id)
                ->whereIn('ability', array_keys(PermissionManagementController::ABILITIES))
                ->delete();
            $now = now();
            DB::table('user_permissions')->insert(array_map(
                fn (string $ability): array => ['user_id' => $staff->id, 'ability' => $ability, 'created_at' => $now, 'updated_at' => $now],
                $abilities,
            ));
        });

        $logger->log('staff.role.assigned', $staff, ['role' => $role, 'abilities' => $abilities, 'restaurant_id' => $restaurantId]);

        return back()->with('success', $staff->name . ' is now a ' . StaffRoles::ROLES[$role]['label'] . '.');
    }

    private function authorizeRoles(Request $request): int
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless($user->can('settings.roles-permissions.manage') || $user->can('admin'), 403);

        return (int) $user->restaurant_id;
    }
}
