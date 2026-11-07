<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Read-only, restaurant-wide audit trail.
 *
 * Rows recorded before activity logs carried a restaurant are attributed
 * through the acting user's restaurant.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_unless($user->can('settings.activity-logs.view') || $user->can('admin'), 403);
        $restaurantId = (int) $user->restaurant_id;

        $filters = $request->validate([
            'area' => ['nullable', 'string', 'max:60', 'regex:/^[a-z_-]+$/'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('restaurant_id', $restaurantId)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $scoped = ActivityLog::query()->where(fn (Builder $query) => $query
            ->where('restaurant_id', $restaurantId)
            ->orWhere(fn (Builder $legacy) => $legacy
                ->whereNull('restaurant_id')
                ->whereIn('user_id', User::query()->where('restaurant_id', $restaurantId)->select('id'))));

        $areas = (clone $scoped)->distinct()->limit(500)->pluck('action')
            ->map(fn (string $action): string => Str::before($action, '.'))
            ->unique()->sort()->values();

        $logs = (clone $scoped)
            ->when($filters['area'] ?? null, fn (Builder $query, string $area) => $query->where('action', 'like', $area . '.%'))
            ->when($filters['user_id'] ?? null, fn (Builder $query, $id) => $query->where('user_id', (int) $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->with('user:id,name,email')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $staff = User::query()->where('restaurant_id', $restaurantId)->orderBy('name')->get(['id', 'name']);

        return view('admin.activity-logs.index', compact('logs', 'areas', 'staff', 'filters'));
    }
}
