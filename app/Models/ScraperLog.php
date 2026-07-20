<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScraperLog extends Model
{
    protected $fillable = [
        'country', 'source_name', 'status', 'items_collected',
        'items_new', 'message', 'ran_at',
    ];

    protected $casts = ['ran_at' => 'datetime'];
}
