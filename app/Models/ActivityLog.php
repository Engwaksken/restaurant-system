<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = ['restaurant_id', 'user_id', 'action', 'subject_type', 'subject_id', 'properties', 'ip_address', 'user_agent'];

    protected $casts = ['properties' => 'array'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
