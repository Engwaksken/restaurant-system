<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFeedback extends Model
{
    protected $table = 'order_feedback';

    protected $fillable = ['branch_id', 'order_id', 'customer_id', 'rating', 'comment'];

    protected $casts = ['rating' => 'integer'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
