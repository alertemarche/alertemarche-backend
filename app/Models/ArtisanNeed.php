<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtisanNeed extends Model
{
    protected $fillable = [
        'publisher_id', 'trade', 'employer_name', 'people_needed', 'description',
        'locality', 'region', 'country', 'estimated_budget', 'duration',
        'start_date', 'contact', 'status', 'validated_by', 'validated_at',
        'ai_summary', 'ai_processed',
    ];

    protected $casts = [
        'start_date' => 'date',
        'validated_at' => 'datetime',
        'ai_processed' => 'boolean',
    ];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }
}
