<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = ['code', 'name', 'flag_emoji', 'currency', 'active'];

    protected $casts = ['active' => 'boolean'];
}
