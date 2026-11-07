<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCompensation extends Model
{
    protected $table = 'staff_compensations';

    protected $fillable = ['user_id', 'branch_id', 'pay_type', 'rate', 'is_active', 'updated_by'];

    protected $casts = ['rate' => 'decimal:2', 'is_active' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
