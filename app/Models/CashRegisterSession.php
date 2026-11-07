<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    protected $fillable = [
        'branch_id', 'opened_by', 'closed_by', 'reference', 'status', 'opening_float', 'expected_cash', 'counted_cash',
        'variance', 'opening_notes', 'closing_notes', 'review_required', 'reviewed_by', 'reviewed_at', 'review_notes',
        'opened_at', 'closed_at',
    ];

    protected $casts = [
        'opening_float' => 'decimal:2', 'expected_cash' => 'decimal:2', 'counted_cash' => 'decimal:2', 'variance' => 'decimal:2',
        'review_required' => 'boolean', 'opened_at' => 'datetime', 'closed_at' => 'datetime', 'reviewed_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function movements(): HasMany { return $this->hasMany(CashMovement::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class); }
}
