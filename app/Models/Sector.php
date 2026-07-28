<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sector extends Model
{
    protected $fillable = ['code', 'name', 'slug', 'icon', 'description', 'type'];

    /** Utilisateurs explicitement rattachés à ce secteur (pivot user_sectors). */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_sectors')->withTimestamps();
    }
}
