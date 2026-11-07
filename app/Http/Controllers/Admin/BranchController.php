<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Restaurant branches. Branch-assigned staff cannot manage branches, and the
 * last active branch cannot be deactivated. Branches are never deleted because
 * orders, stock and finance records belong to them.
 */
class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authorizeBranches($request);
        $branches = Branch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->withCount(['staff as staff_count' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $all = Branch::query()->where('restaurant_id', $user->restaurant_id);
        $pageStats = [
            ['label' => 'Branches', 'value' => number_format((clone $all)->count()), 'icon' => 'fas fa-code-branch', 'tone' => 'brand'],
            ['label' => 'Active', 'value' => number_format((clone $all)->where('is_active', true)->count()), 'icon' => 'fas fa-circle-check', 'tone' => 'success'],
            ['label' => 'Inactive', 'value' => number_format((clone $all)->where('is_active', false)->count()), 'icon' => 'fas fa-circle-pause', 'tone' => 'neutral'],
            ['label' => 'Active staff', 'value' => number_format(User::query()->where('restaurant_id', $user->restaurant_id)->where('is_active', true)->count()), 'icon' => 'fas fa-users', 'tone' => 'info', 'href' => route('admin.staff.index')],
        ];

        return view('admin.settings.branches', compact('branches', 'pageStats'));
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeBranches($request);
        $data = $request->validate($this->rules($user));
        $branch = Branch::create([...$data, 'restaurant_id' => $user->restaurant_id, 'is_active' => true]);
        $logger->log('settings.branch.created', $branch, ['name' => $branch->name]);

        return back()->with('success', 'Branch ' . $branch->name . ' created.');
    }

    public function update(Request $request, int $branch, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeBranches($request);
        $record = Branch::query()->where('restaurant_id', $user->restaurant_id)->findOrFail($branch);
        $data = $request->validate($this->rules($user, $record->id));
        $record->update($data);
        $logger->log('settings.branch.updated', $record, ['fields' => array_keys($record->getChanges())]);

        return back()->with('success', 'Branch ' . $record->name . ' updated.');
    }

    public function updateStatus(Request $request, int $branch, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeBranches($request);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $active = (bool) $data['is_active'];

        $record = DB::transaction(function () use ($user, $branch, $active): Branch {
            $branches = Branch::query()->where('restaurant_id', $user->restaurant_id)->lockForUpdate()->get();
            $record = $branches->firstWhere('id', $branch) ?? abort(404);
            if (!$active && $record->is_active && $branches->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['branch' => 'At least one branch must stay active.']);
            }
            if (!$active && DB::table('cash_register_sessions')->where('branch_id', $record->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['branch' => 'Close this branch\'s open cash register sessions first.']);
            }
            $record->update(['is_active' => $active]);

            return $record;
        });
        $logger->log($active ? 'settings.branch.activated' : 'settings.branch.deactivated', $record);

        return back()->with('success', 'Branch ' . $record->name . ($active ? ' activated.' : ' deactivated.'));
    }

    private function rules(User $user, ?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->where('restaurant_id', $user->restaurant_id)->ignore($ignoreId)],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('branches', 'code')->ignore($ignoreId)],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function authorizeBranches(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_if($user->branch_id !== null, 403, 'Branch-assigned staff cannot manage restaurant branches.');
        abort_unless($user->can('settings.branches.manage') || $user->can('admin'), 403);

        return $user;
    }
}
