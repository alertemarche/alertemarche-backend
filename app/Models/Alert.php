<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'user_id', 'source_type', 'source_id', 'title', 'message',
        'relevance_score', 'sent_email', 'sent_whatsapp', 'sent_at',
        'is_free', 'status',
    ];

    protected $casts = [
        'sent_email' => 'boolean',
        'sent_whatsapp' => 'boolean',
        'is_free' => 'boolean',
        'sent_at' => 'datetime',
        'relevance_score' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
