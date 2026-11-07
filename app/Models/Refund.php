<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'branch_id', 'order_id', 'payment_id', 'cash_register_session_id', 'refunded_by', 'refund_number',
        'method', 'amount', 'reason', 'reference',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function refunder(): BelongsTo { return $this->belongsTo(User::class, 'refunded_by'); }
}
