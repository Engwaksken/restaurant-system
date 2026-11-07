<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    protected $fillable = ['branch_id', 'user_id', 'created_by', 'starts_at', 'ends_at', 'role_label', 'notes'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
