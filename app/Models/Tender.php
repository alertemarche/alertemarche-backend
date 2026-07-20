<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tender extends Model
{
    protected $fillable = [
        'title', 'institution', 'estimated_amount', 'deadline', 'country', 'type',
        'source_name', 'source_url', 'sectors', 'ai_summary', 'ai_processed',
        'dedup_hash', 'collected_at',
    ];

    protected $casts = [
        'sectors' => 'array',
        'ai_processed' => 'boolean',
        'deadline' => 'date',
        'collected_at' => 'datetime',
    ];
}
