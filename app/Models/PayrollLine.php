<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollLine extends Model
{
    protected $fillable = [
        'payroll_run_id', 'user_id', 'employee_name', 'pay_type', 'rate', 'hours', 'base_pay', 'allowances',
        'gross_pay', 'deductions', 'net_pay',
    ];

    protected $casts = [
        'rate' => 'decimal:2', 'hours' => 'decimal:2', 'base_pay' => 'decimal:2', 'allowances' => 'decimal:2',
        'gross_pay' => 'decimal:2', 'deductions' => 'decimal:2', 'net_pay' => 'decimal:2',
    ];

    public function run(): BelongsTo { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function adjustments(): HasMany { return $this->hasMany(PayrollAdjustment::class); }
}
