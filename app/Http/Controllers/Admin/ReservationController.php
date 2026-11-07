<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesToUserBranches;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Table reservations scoped to the user's restaurant/branch.
 *
 * A table holds a booking for `restaurant.reservation_duration_minutes` either
 * side of its reservation time. Conflict checks lock the table row so two
 * concurrent bookings cannot both succeed.
 */
class ReservationController extends Controller
{
    use ScopesToUserBranches;

    public function index(Request $request): View
    {
        $user = $this->authorizeReservations($request, 'view');
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(array_keys(Reservation::STATUS_TRANSITIONS))],
        ]);
        $date = Carbon::parse($filters['date'] ?? now()->toDateString());

        $reservations = $this->whereUserBranch(Reservation::query(), $user)
            ->with(['branch:id,name', 'table:id,name,capacity'])
            ->whereBetween('reserved_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('reserved_at')->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $tables = $this->whereUserBranch(RestaurantTable::query(), $user)
            ->where('status', '!=', 'inactive')
            ->with('branch:id,name')
            ->orderBy('branch_id')->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'capacity']);

        return view('admin.tables.reservations', [
            'reservations' => $reservations,
            'tables' => $tables,
            'branches' => $this->userBranches($user),
            'date' => $date,
            'status' => $filters['status'] ?? null,
            'canManage' => $this->canManage($request),
            'transitions' => Reservation::STATUS_TRANSITIONS,
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeReservations($request, 'manage');
        $data = $request->validate([
            'branch_id' => ['required', 'integer', $this->userBranchRule($user)],
            ...$this->detailRules(),
            'reserved_at' => ['required', 'date', 'after:now'],
        ]);

        $reservation = DB::transaction(function () use ($data, $user): Reservation {
            $at = Carbon::parse($data['reserved_at']);
            $this->assertTableAvailable((int) $data['branch_id'], $data['restaurant_table_id'] ?? null, $at, (int) $data['guest_count']);

            return Reservation::create([
                'branch_id' => $data['branch_id'],
                'customer_id' => $this->customerFor($user, $data['phone'], $data['guest_name'])->id,
                'restaurant_table_id' => $data['restaurant_table_id'] ?? null,
                'reservation_number' => $this->uniqueNumber(),
                'guest_name' => $data['guest_name'],
                'phone' => $data['phone'],
                'guest_count' => $data['guest_count'],
                'reserved_at' => $at,
                'status' => 'pending',
                'special_requests' => $data['special_requests'] ?? null,
            ]);
        });
        $logger->log('reservations.created', $reservation, ['branch_id' => $reservation->branch_id]);

        return redirect()
            ->route('admin.reservations.index', ['date' => $reservation->reserved_at->toDateString()])
            ->with('success', 'Reservation ' . $reservation->reservation_number . ' created.');
    }

    public function update(Request $request, int $reservation, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeReservations($request, 'manage');
        $data = $request->validate([
            ...$this->detailRules(),
            'reserved_at' => ['required', 'date'],
        ]);

        $record = DB::transaction(function () use ($user, $reservation, $data): Reservation {
            $record = $this->whereUserBranch(Reservation::query(), $user)->lockForUpdate()->findOrFail($reservation);
            if (!in_array($record->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages(['reservation' => 'Only pending or confirmed reservations can be changed.']);
            }
            $at = Carbon::parse($data['reserved_at']);
            if (!$at->equalTo($record->reserved_at) && $at->isPast()) {
                throw ValidationException::withMessages(['reserved_at' => 'A rescheduled reservation must be in the future.']);
            }
            $this->assertTableAvailable($record->branch_id, $data['restaurant_table_id'] ?? null, $at, (int) $data['guest_count'], $record->id);

            $phone = trim($data['phone']);
            $record->update([
                'customer_id' => $phone === $record->phone && $record->customer_id
                    ? $record->customer_id
                    : $this->customerFor($user, $phone, $data['guest_name'])->id,
                'restaurant_table_id' => $data['restaurant_table_id'] ?? null,
                'guest_name' => $data['guest_name'],
                'phone' => $data['phone'],
                'guest_count' => $data['guest_count'],
                'reserved_at' => $at,
                'special_requests' => $data['special_requests'] ?? null,
            ]);

            return $record;
        });
        $logger->log('reservations.updated', $record, ['branch_id' => $record->branch_id]);

        return back()->with('success', 'Reservation ' . $record->reservation_number . ' updated.');
    }

    public function updateStatus(Request $request, int $reservation, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeReservations($request, 'manage');
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(Reservation::STATUS_TRANSITIONS))],
        ]);

        [$record, $from] = DB::transaction(function () use ($user, $reservation, $data): array {
            $record = $this->whereUserBranch(Reservation::query(), $user)->lockForUpdate()->findOrFail($reservation);
            abort_unless($record->canTransitionTo($data['status']), 422, 'Invalid reservation status transition.');
            $from = $record->status;

            if ($data['status'] === 'seated') {
                $table = $record->restaurant_table_id
                    ? RestaurantTable::query()->lockForUpdate()->find($record->restaurant_table_id)
                    : null;
                if (!$table) {
                    throw ValidationException::withMessages(['status' => 'Assign a table before seating this reservation.']);
                }
                if (!in_array($table->status, ['available', 'reserved'], true)) {
                    throw ValidationException::withMessages(['status' => 'Table ' . $table->name . ' is ' . $table->status . ' and cannot be seated.']);
                }
                $table->update(['status' => 'occupied']);
            }

            if ($data['status'] === 'completed' && $record->restaurant_table_id) {
                // The party has left: the table needs clearing before the next seating.
                RestaurantTable::query()
                    ->whereKey($record->restaurant_table_id)
                    ->where('status', 'occupied')
                    ->update(['status' => 'cleaning', 'updated_at' => now()]);
            }

            $record->update(['status' => $data['status']]);

            return [$record, $from];
        });
        $logger->log('reservations.status.' . $data['status'], $record, ['from' => $from, 'to' => $data['status']]);

        return back()->with('success', 'Reservation ' . $record->reservation_number . ' marked ' . str_replace('_', ' ', $data['status']) . '.');
    }

    private function detailRules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\s-]{6,40}$/'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:500'],
            'restaurant_table_id' => ['nullable', 'integer'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Must run inside a transaction: locks the table row while checking for conflicts. */
    private function assertTableAvailable(int $branchId, mixed $tableId, Carbon $at, int $guests, ?int $ignoreId = null): void
    {
        if ($tableId === null || $tableId === '') {
            return;
        }

        $table = RestaurantTable::query()
            ->whereKey((int) $tableId)
            ->where('branch_id', $branchId)
            ->where('status', '!=', 'inactive')
            ->lockForUpdate()
            ->first();
        if (!$table) {
            throw ValidationException::withMessages(['restaurant_table_id' => 'Select an active table in the reservation\'s branch.']);
        }
        if ($guests > $table->capacity) {
            throw ValidationException::withMessages(['guest_count' => 'Table ' . $table->name . ' seats at most ' . $table->capacity . ' guests.']);
        }

        $window = max(1, (int) config('restaurant.reservation_duration_minutes', 120));
        $conflict = Reservation::query()
            ->where('restaurant_table_id', $table->id)
            ->whereIn('status', Reservation::ACTIVE_STATUSES)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('reserved_at', '>', $at->copy()->subMinutes($window))
            ->where('reserved_at', '<', $at->copy()->addMinutes($window))
            ->orderBy('reserved_at')
            ->first();
        if ($conflict) {
            throw ValidationException::withMessages(['restaurant_table_id' => 'Table ' . $table->name . ' is already booked at '
                . $conflict->reserved_at->format('H:i') . ' (' . $conflict->reservation_number . ').']);
        }
    }

    /** Link to the restaurant's customer with this phone, creating one like public orders do. */
    private function customerFor(User $user, string $phone, string $name): Customer
    {
        return Customer::query()->firstOrCreate(
            ['restaurant_id' => $user->restaurant_id, 'phone' => trim($phone)],
            ['name' => $name],
        );
    }

    private function uniqueNumber(): string
    {
        do {
            $number = 'RSV-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        } while (Reservation::query()->where('reservation_number', $number)->exists());

        return $number;
    }

    private function authorizeReservations(Request $request, string $operation): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        $allowed = $operation === 'view'
            ? $user->can('reservations.view') || $this->canManage($request)
            : $this->canManage($request);
        abort_unless($allowed, 403);

        return $user;
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user && ($user->can('reservations.manage') || $user->can('admin'));
    }
}
