<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = ['restaurant_id', 'customer_id', 'assigned_to', 'session_token', 'status', 'last_activity_at', 'rating'];

    protected $casts = ['last_activity_at' => 'datetime'];

    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages(): HasMany { return $this->hasMany(ChatMessage::class); }
}
