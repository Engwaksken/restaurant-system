<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'inventory_item_id', 'quantity', 'unit_cost', 'line_total'];

    protected $casts = ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
    public function purchaseOrderItem(): BelongsTo { return $this->belongsTo(PurchaseOrderItem::class); }
    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class); }
}
