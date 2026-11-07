<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['restaurant_id', 'menu_category_id', 'name', 'sku', 'description', 'price', 'cost_price', 'image_path', 'is_available', 'track_inventory', 'preparation_minutes'];

    protected $casts = ['price' => 'decimal:2', 'cost_price' => 'decimal:2', 'is_available' => 'boolean', 'track_inventory' => 'boolean'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        $path = trim((string) $this->image_path);

        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $path = ltrim($path, '/');
        foreach (['storage/', 'public/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        return Storage::disk('public')->url($path);
    }

    public function category(): BelongsTo { return $this->belongsTo(MenuCategory::class, 'menu_category_id'); }
    public function variants(): HasMany { return $this->hasMany(MenuItemVariant::class)->orderBy('display_order')->orderBy('price'); }
    public function addons(): HasMany { return $this->hasMany(MenuItemAddon::class)->orderBy('display_order')->orderBy('name'); }
}
