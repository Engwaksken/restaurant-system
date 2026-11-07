<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class Floor extends Model {
    protected $fillable = ['branch_id', 'name', 'display_order'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function tables(): HasMany { return $this->hasMany(RestaurantTable::class); }
}
