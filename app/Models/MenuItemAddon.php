<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemAddon extends Model
{
    protected $fillable = ['menu_item_id', 'name', 'price', 'is_available', 'display_order'];

    protected $casts = ['price' => 'decimal:2', 'is_available' => 'boolean'];

    public function menuItem(): BelongsTo { return $this->belongsTo(MenuItem::class); }
}
