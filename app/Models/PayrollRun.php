<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'branch_id', 'reference', 'period_start', 'period_end', 'status', 'gross_total', 'deductions_total', 'net_total',
        'prepared_by', 'approved_by', 'approved_at', 'paid_by', 'paid_at', 'payment_reference', 'expense_id',
    ];

    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date', 'gross_total' => 'decimal:2', 'deductions_total' => 'decimal:2',
        'net_total' => 'decimal:2', 'approved_at' => 'datetime', 'paid_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function lines(): HasMany { return $this->hasMany(PayrollLine::class); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function payer(): BelongsTo { return $this->belongsTo(User::class, 'paid_by'); }
}
