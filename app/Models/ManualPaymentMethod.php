<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Offline payment option an admin adds, e.g. "MTN MoMo merchant code" or "Bank transfer". */
class ManualPaymentMethod extends Model
{
    protected $fillable = ['restaurant_id', 'name', 'icon', 'instructions', 'is_active', 'display_order'];

    protected $casts = ['is_active' => 'boolean'];

    /** Value stored in orders.payment_preference when a customer picks this method. */
    public function optionKey(): string
    {
        return 'manual_' . $this->id;
    }
}
