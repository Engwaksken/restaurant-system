<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id', 'expense_category_id', 'created_by', 'reference',
        'description', 'amount', 'payment_method', 'expense_date', 'attachment_path',
    ];

    protected $casts = ['amount' => 'decimal:2', 'expense_date' => 'date'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
