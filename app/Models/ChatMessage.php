<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = ['chat_conversation_id', 'user_id', 'sender_type', 'message', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function conversation(): BelongsTo { return $this->belongsTo(ChatConversation::class, 'chat_conversation_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
