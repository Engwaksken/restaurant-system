<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewaySetting extends Model
{
    protected $table = 'restaurant_payment_gateways';

    protected $fillable = ['restaurant_id', 'gateway', 'is_enabled', 'mode', 'credentials', 'updated_by'];

    protected $hidden = ['credentials'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'credentials' => 'encrypted:array',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
