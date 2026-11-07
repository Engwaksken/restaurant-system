<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['branch_id', 'user_id', 'clock_in_at', 'clock_out_at', 'recorded_by', 'edited_by', 'note'];

    protected $casts = ['clock_in_at' => 'datetime', 'clock_out_at' => 'datetime'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'edited_by'); }

    public function hours(): float
    {
        return $this->clock_out_at ? round($this->clock_in_at->diffInMinutes($this->clock_out_at) / 60, 2) : 0.0;
    }
}
