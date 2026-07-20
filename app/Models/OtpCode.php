<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = ['identifier', 'channel', 'code', 'expires_at', 'consumed_at'];

    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];

    public function isValid(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
