<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan', 'duration_months', 'profile_type', 'countries',
        'country_count', 'base_price', 'amount', 'promo_applied', 'status',
        'started_at', 'expires_at', 'auto_renew', 'payment_reference',
    ];

    protected $casts = [
        'countries' => 'array',
        'promo_applied' => 'boolean',
        'auto_renew' => 'boolean',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
