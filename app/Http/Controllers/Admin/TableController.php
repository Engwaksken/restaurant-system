<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesToUserBranches;
use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\RestaurantTable;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Floors/sections and restaurant tables, scoped to the user's restaurant/branch.
 *
 * Tables are deactivated instead of deleted so order and reservation history
 * keeps its table reference.
 */
class TableController extends Controller
{
    use ScopesToUserBranches;

    public function floors(Request $request): View
    {
        $user = $this->authorizeTables($request, 'view');
        $floors = $this->whereUserBranch(Floor::query(), $user)
            ->with('branch:id,name')
            ->withCount('tables')
            ->orderBy('branch_id')->orderBy('display_order')->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.tables.floors', [
            'pageStats' => $this->seatingStats($user),
            'floors' => $floors,
            'branches' => $this->userBranches($user),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function storeFloor(Request $request): RedirectResponse
    {
        $user = $this->authorizeTables($request, 'manage');
        $data = $request->validate([
            'branch_id' => ['required', 'integer', $this->userBranchRule($user)],
            'name' => ['required', 'string', 'max:255', Rule::unique('floors', 'name')->where('branch_id', $request->integer('branch_id'))],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        Floor::create([...$data, 'display_order' => $data['display_order'] ?? 0]);

        return back()->with('success', 'Floor / section created.');
    }

    public function updateFloor(Request $request, int $floor): RedirectResponse
    {
        $user = $this->authorizeTables($request, 'manage');
        $record = $this->whereUserBranch(Floor::query(), $user)->findOrFail($floor);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('floors', 'name')->where('branch_id', $record->branch_id)->ignore($record->id)],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $record->update([...$data, 'display_order' => $data['display_order'] ?? 0]);

        return back()->with('success', 'Floor / section updated.');
    }

    public function destroyFloor(Request $request, int $floor): RedirectResponse
    {
        $user = $this->authorizeTables($request, 'manage');
        $record = $this->whereUserBranch(Floor::query(), $user)->findOrFail($floor);
        if ($record->tables()->exists()) {
            return back()->withErrors(['floor' => 'Move this section\'s tables to another section before deleting it.']);
        }
        $record->delete();

        return back()->with('success', 'Floor / section deleted.');
    }

    public function tables(Request $request): View
    {
        $user = $this->authorizeTables($request, 'view');
        $tables = $this->whereUserBranch(RestaurantTable::query(), $user)
            ->with(['branch:id,name', 'floor:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('branch_id')->orderBy('floor_id')->orderBy('name')
            ->paginate(50)
            ->withQueryString();
        $floors = $this->whereUserBranch(Floor::query(), $user)
            ->orderBy('display_order')->orderBy('name')->get(['id', 'branch_id', 'name']);

        return view('admin.tables.tables', [
            'pageStats' => $this->seatingStats($user),
            'tables' => $tables,
            'floors' => $floors,
            'branches' => $this->userBranches($user),
            'canManage' => $this->canManage($request),
            'manualStatuses' => RestaurantTable::MANUAL_STATUSES,
        ]);
    }

    /** QR ordering setup for tables in the signed-in user's branch scope. */
    public function qrOrdering(Request $request): View
    {
        $user = $this->authorizeTables($request, 'view');
        $tables = $this->whereUserBranch(RestaurantTable::query(), $user)
            ->with(['branch:id,name', 'floor:id,name'])
            ->orderBy('branch_id')->orderBy('name')->paginate(50)->withQueryString();

        return view('admin.tables.qr-ordering', ['tables' => $tables, 'canManage' => $this->canManage($request)]);
    }

    public function createQr(Request $request, int $table): RedirectResponse
    {
        $user = $this->authorizeTables($request, 'manage');
        $record = $this->whereUserBranch(RestaurantTable::query(), $user)->findOrFail($table);
        if (!$record->qr_token) {
            $record->forceFill(['qr_token' => Str::random(48)])->save();
        }

        return back()->with('success', 'QR ordering link created for ' . $record->name . '.');
    }

    public function storeTable(Request $request): RedirectResponse
    {
        $user = $this->authorizeTables($request, 'manage');
        $branchId = $request->integer('branch_id');
        $data = $request->validate([
            'branch_id' => ['required', 'integer', $this->userBranchRule($user)],
            'floor_id' => ['nullable', 'integer', Rule::exists('floors', 'id')->where('branch_id', $branchId)],
            'name' => ['required', 'string', 'max:255', Rule::unique('restaurant_tables', 'name')->where('branch_id', $branchId)],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        RestaurantTable::create([...$data, 'code' => $this->uniqueCode(), 'status' => 'available']);

        return back()->with('success', 'Table created.');
    }

    public function updateTable(Request $request, int $table): RedirectResponse
    {
        $user = $this->authorizeTables($request, 'manage');
        $record = $this->whereUserBranch(RestaurantTable::query(), $user)->findOrFail($table);
        $data = $request->validate([
            'floor_id' => ['nullable', 'integer', Rule::exists('floors', 'id')->where('branch_id', $record->branch_id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('restaurant_tables', 'name')->where('branch_id', $record->branch_id)->ignore($record->id)],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        $record->update($data);

        return back()->with('success', 'Table updated.');
    }

    public function updateTableStatus(Request $request, int $table, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeTables($request, 'manage');
        $record = $this->whereUserBranch(RestaurantTable::query(), $user)->findOrFail($table);
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(RestaurantTable::MANUAL_STATUSES)],
        ]);
        $previous = $record->status;
        $record->update(['status' => $data['status']]);
        $logger->log('tables.status.updated', $record, ['from' => $previous, 'to' => $data['status']]);

        return back()->with('success', 'Table ' . $record->name . ' is now ' . str_replace('_', ' ', $data['status']) . '.');
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'TBL-' . Str::upper(Str::random(8));
        } while (RestaurantTable::query()->where('code', $code)->exists());

        return $code;
    }

    private function authorizeTables(Request $request, string $operation): \App\Models\User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        $allowed = $operation === 'view'
            ? $user->can('tables.view') || $this->canManage($request)
            : $this->canManage($request);
        abort_unless($allowed, 403);

        return $user;
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user && ($user->can('tables.manage') || $user->can('admin'));
    }

    /** Seating overview shared by the floors and tables pages. */
    private function seatingStats($user): array
    {
        $tables = $this->whereUserBranch(RestaurantTable::query(), $user);
        $active = (clone $tables)->where('status', '!=', 'inactive');
        $total = (clone $active)->count();
        $occupied = (clone $tables)->where('status', 'occupied')->count();

        return [
            ['label' => 'Tables in service', 'value' => number_format($total), 'icon' => 'fas fa-chair', 'tone' => 'brand', 'hint' => number_format((int) (clone $active)->sum('capacity')) . ' seats'],
            ['label' => 'Available now', 'value' => number_format((clone $tables)->where('status', 'available')->count()), 'icon' => 'fas fa-circle-check', 'tone' => 'success'],
            ['label' => 'Occupied', 'value' => number_format($occupied), 'icon' => 'fas fa-utensils', 'tone' => 'warning', 'hint' => $total ? round($occupied / $total * 100) . '% occupancy' : null],
            ['label' => 'Floors / sections', 'value' => number_format($this->whereUserBranch(Floor::query(), $user)->count()), 'icon' => 'fas fa-building', 'tone' => 'info'],
        ];
    }
}
