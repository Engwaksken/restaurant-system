<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = ['purchase_order_id', 'branch_id', 'supplier_id', 'created_by', 'reference', 'invoice_number', 'total', 'received_at'];

    protected $casts = ['total' => 'decimal:2', 'received_at' => 'datetime'];

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseItem::class); }
    public function supplierPayments(): HasMany { return $this->hasMany(SupplierPayment::class); }
}
