<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = ['from_branch_id', 'to_branch_id', 'created_by', 'reference', 'notes', 'transferred_at'];

    protected $casts = ['transferred_at' => 'datetime'];

    public function fromBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'from_branch_id'); }
    public function toBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'to_branch_id'); }
    public function items(): HasMany { return $this->hasMany(StockTransferItem::class); }
}
