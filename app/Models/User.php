<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const PROFILES = ['prestataire', 'artisan', 'admin_public', 'ong'];

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'profile_type', 'is_admin',
        'primary_country', 'sectors', 'keywords', 'artisan_trade', 'artisan_locality',
        'artisan_radius_km', 'notify_whatsapp', 'notify_email',
        'email_verified_at', 'phone_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'sectors' => 'array',
            'keywords' => 'array',
            'is_admin' => 'boolean',
            'is_suspended' => 'boolean',
            'notify_whatsapp' => 'boolean',
            'notify_email' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function publishedNeeds(): HasMany
    {
        return $this->hasMany(ArtisanNeed::class, 'publisher_id');
    }

    /** Abonnement actif (le cas échéant). */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    /** Quota freemium restant. */
    public function freeAlertsRemaining(): int
    {
        $quota = (int) config('alertemarche.freemium_alerts', 5);

        return max(0, $quota - (int) $this->free_alerts_used);
    }

    /** L'utilisateur peut-il recevoir une nouvelle alerte ? */
    public function canReceiveAlert(): bool
    {
        if ($this->hasActiveSubscription()) {
            return true;
        }

        return $this->freeAlertsRemaining() > 0;
    }

    /** WhatsApp uniquement disponible avec abonnement payant actif. */
    public function whatsappEnabled(): bool
    {
        return $this->notify_whatsapp && $this->hasActiveSubscription();
    }
}
