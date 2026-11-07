<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Reservation extends Model {
    /** Reservations in these statuses hold their table for the booking window. */
    public const ACTIVE_STATUSES = ['pending', 'confirmed', 'seated'];

    public const STATUS_TRANSITIONS = [
        'pending' => ['confirmed', 'seated', 'cancelled', 'no_show'],
        'confirmed' => ['seated', 'cancelled', 'no_show'],
        'seated' => ['completed'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    protected $fillable = ['branch_id', 'customer_id', 'restaurant_table_id', 'reservation_number', 'guest_name', 'phone', 'guest_count', 'reserved_at', 'status', 'deposit_amount', 'special_requests'];
    protected $casts = ['reserved_at' => 'datetime', 'guest_count' => 'integer', 'deposit_amount' => 'decimal:2'];

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function table(): BelongsTo { return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id'); }
}
