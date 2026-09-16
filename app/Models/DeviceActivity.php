<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un appareil ayant interagi avec l'API (utilisateur connecté).
 * Une ligne par empreinte (user_id + user-agent), mise à jour à
 * chaque requête authentifiée via le middleware TrackDeviceActivity.
 */
class DeviceActivity extends Model
{
    protected $table = 'device_activity';

    protected $fillable = [
        'user_id',
        'fingerprint',
        'device_type',
        'browser',
        'platform',
        'user_agent',
        'ip',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
