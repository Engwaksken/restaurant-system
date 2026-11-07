<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'restaurant_id', 'inventory_category_id', 'supplier_id', 'name', 'sku', 'barcode',
        'purchase_unit', 'usage_unit', 'conversion_factor', 'unit_cost',
        'minimum_stock', 'reorder_level', 'track_expiry', 'is_active',
    ];

    protected $casts = ['track_expiry' => 'boolean', 'is_active' => 'boolean'];
}
