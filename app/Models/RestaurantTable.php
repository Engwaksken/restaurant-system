<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class RestaurantTable extends Model {
    /** Statuses staff may set directly; `reserved` is driven by reservations. */
    public const MANUAL_STATUSES = ['available', 'occupied', 'cleaning', 'inactive'];

    protected $fillable = ['branch_id', 'floor_id', 'name', 'code', 'capacity', 'status', 'qr_token'];
    protected $casts = ['capacity' => 'integer'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function floor(): BelongsTo { return $this->belongsTo(Floor::class); }
    public function reservations(): HasMany { return $this->hasMany(Reservation::class); }
}
