<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAdjustment extends Model
{
    protected $fillable = ['payroll_line_id', 'type', 'label', 'amount', 'is_statutory', 'created_by'];

    protected $casts = ['amount' => 'decimal:2', 'is_statutory' => 'boolean'];

    public function line(): BelongsTo { return $this->belongsTo(PayrollLine::class, 'payroll_line_id'); }
}
