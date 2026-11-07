<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'menu_item_id', 'menu_item_variant_id', 'item_name', 'variant_name', 'addons',
        'unit_price', 'quantity', 'discount', 'tax', 'total', 'notes', 'kitchen_status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2', 'quantity' => 'decimal:3', 'discount' => 'decimal:2', 'tax' => 'decimal:2',
        'total' => 'decimal:2', 'addons' => 'array',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function menuItem(): BelongsTo { return $this->belongsTo(MenuItem::class); }

    /** Display name including the chosen variant, e.g. "Pizza (Large)". */
    public function displayName(): string
    {
        return $this->item_name . ($this->variant_name ? ' (' . $this->variant_name . ')' : '');
    }
}
