<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'customer_id', 'received_by', 'cash_register_session_id', 'receipt_number', 'payment_method',
        'amount', 'transaction_reference', 'status', 'gateway_response', 'paid_at',
        'reconciliation_status', 'statement_reference', 'reconciliation_note', 'reconciled_by', 'reconciled_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'gateway_response' => 'array', 'paid_at' => 'datetime', 'reconciled_at' => 'datetime'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function reconciler(): BelongsTo { return $this->belongsTo(User::class, 'reconciled_by'); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class); }

    public function refundableAmount(): float
    {
        return max(0, round((float) $this->amount - (float) $this->refunds()->sum('amount'), 2));
    }
}
