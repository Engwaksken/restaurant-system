<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wastage extends Model
{
    protected $fillable = ['branch_id', 'inventory_item_id', 'created_by', 'quantity', 'unit_cost', 'reason', 'reference', 'wasted_at'];

    protected $casts = ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'wasted_at' => 'datetime'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class); }
}
