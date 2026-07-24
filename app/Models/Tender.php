<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tender extends Model
{
    protected $fillable = [
        'title', 'title_fr', 'institution', 'reference', 'location', 'estimated_amount',
        'deadline', 'publication_date', 'nb_lots', 'country', 'type', 'market_type', 'procedure_type',
        'source_name', 'source_url', 'dao_url', 'sectors', 'ai_summary', 'ai_processed',
        'ocr_processed', 'dedup_hash', 'external_id', 'collected_at',
    ];

    protected $casts = [
        'sectors' => 'array',
        'ai_processed' => 'boolean',
        'ocr_processed' => 'boolean',
        'deadline' => 'date',
        'publication_date' => 'date',
        'collected_at' => 'datetime',
    ];
}
