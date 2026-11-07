<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['restaurant_id', 'menu_category_id', 'name', 'sku', 'description', 'price', 'cost_price', 'image_path', 'is_available', 'track_inventory', 'preparation_minutes'];

    protected $casts = ['price' => 'decimal:2', 'cost_price' => 'decimal:2', 'is_available' => 'boolean', 'track_inventory' => 'boolean'];

    public function category(): BelongsTo { return $this->belongsTo(MenuCategory::class, 'menu_category_id'); }
    public function variants(): HasMany { return $this->hasMany(MenuItemVariant::class)->orderBy('display_order')->orderBy('price'); }
    public function addons(): HasMany { return $this->hasMany(MenuItemAddon::class)->orderBy('display_order')->orderBy('name'); }
}
