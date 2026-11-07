<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesToUserBranches;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Staff login accounts. Permissions are granted separately on the Roles &
 * Permissions screen. Branch managers only see and assign their own branch.
 * Accounts are deactivated rather than deleted so audit history keeps its
 * actor; wildcard administrators remain CLI-managed.
 */
class StaffController extends Controller
{
    use ScopesToUserBranches;

    public function index(Request $request): View
    {
        $user = $this->authorizeStaff($request);
        $staff = $this->manageableStaff($user)
            ->with('branch:id,name')
            ->when($request->query('show') !== 'all', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();
        $wildcardIds = DB::table('user_permissions')->where('ability', 'admin')
            ->whereIn('user_id', $staff->pluck('id'))->pluck('user_id')->all();

        return view('admin.employees.staff', [
            'staff' => $staff,
            'branches' => $this->userBranches($user),
            'wildcardIds' => $wildcardIds,
            'restaurantWide' => $user->branch_id === null,
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeStaff($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(12)->letters()->numbers(), 'confirmed'],
            'branch_id' => $this->branchRules($user),
        ]);

        $member = new User();
        $member->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $data['branch_id'] ?? $user->branch_id,
            'is_active' => true,
        ])->save();
        $logger->log('staff.account.created', $member, ['branch_id' => $member->branch_id]);

        return back()->with('success', 'Account created for ' . $member->name . '. Grant permissions on the Roles & Permissions screen.');
    }

    public function update(Request $request, int $staff, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeStaff($request);
        $member = $this->findManageable($user, $staff);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member->id)],
            'branch_id' => $this->branchRules($user),
        ]);
        abort_if($member->id === $user->id && ($data['branch_id'] ?? null) != $user->branch_id, 422, 'You cannot change your own branch assignment.');

        $member->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'branch_id' => $data['branch_id'] ?? $user->branch_id,
        ])->save();
        $logger->log('staff.account.updated', $member, ['fields' => array_keys($member->getChanges())]);

        return back()->with('success', $member->name . ' updated.');
    }

    public function updateStatus(Request $request, int $staff, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeStaff($request);
        $member = $this->findManageable($user, $staff);
        $active = (bool) $request->validate(['is_active' => ['required', 'boolean']])['is_active'];
        abort_if($member->id === $user->id, 422, 'You cannot deactivate your own account.');

        $member->forceFill(['is_active' => $active, 'deactivated_at' => $active ? null : now()])->save();
        if (!$active) {
            // End any remembered/database sessions immediately.
            $member->forceFill(['remember_token' => null])->save();
            DB::table('sessions')->where('user_id', $member->id)->delete();
        }
        $logger->log($active ? 'staff.account.reactivated' : 'staff.account.deactivated', $member);

        return back()->with('success', $member->name . ($active ? ' reactivated.' : ' deactivated and signed out.'));
    }

    public function resetPassword(Request $request, int $staff, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeStaff($request);
        $member = $this->findManageable($user, $staff);
        $data = $request->validate([
            'password' => ['required', 'string', Password::min(12)->letters()->numbers(), 'confirmed'],
        ]);
        $member->forceFill(['password' => $data['password'], 'remember_token' => null])->save();
        if ($member->id !== $user->id) {
            DB::table('sessions')->where('user_id', $member->id)->delete();
        }
        $logger->log('staff.account.password_reset', $member);

        return back()->with('success', 'Password reset for ' . $member->name . '. Share it with them securely.');
    }

    private function branchRules(User $user): array
    {
        // Branch managers can only assign their own branch; restaurant-wide managers may leave it empty.
        return $user->branch_id
            ? ['required', 'integer', Rule::in([$user->branch_id])]
            : ['nullable', 'integer', $this->userBranchRule($user)];
    }

    private function manageableStaff(User $user): Builder
    {
        return User::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function findManageable(User $user, int $id): User
    {
        $member = $this->manageableStaff($user)->findOrFail($id);
        abort_if($member->isWildcardAdmin(), 422, 'Wildcard administrators must be managed through reviewed CLI provisioning.');

        return $member;
    }

    private function authorizeStaff(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_unless($user->can('employees.staff.manage') || $user->can('admin'), 403);

        return $user;
    }
}
