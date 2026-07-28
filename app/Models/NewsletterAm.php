<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Campagne e-mail AlerteMarché (newsletter ou annonce publicitaire).
 */
class NewsletterAm extends Model
{
    protected $table = 'newsletters_am';

    protected $fillable = [
        'subject', 'body', 'type', 'target_type',
        'target_sector_id', 'target_country', 'sent_count', 'status', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'sent_count' => 'integer',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'target_sector_id');
    }

    /**
     * Construit la requête des destinataires selon le ciblage de la campagne.
     * Base : utilisateurs avec e-mail renseigné et non suspendus.
     *
     * - all        : tous les abonnés.
     * - by_sector  : secteur déclaré (JSON users.sectors) OU pivot user_sectors.
     * - by_country : pays principal (ALL ou vide = tous les pays).
     */
    public function recipientsQuery(): Builder
    {
        $q = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($w) {
                $w->whereNull('is_suspended')->orWhere('is_suspended', false);
            });

        if ($this->target_type === 'by_sector' && $this->target_sector_id) {
            $sector = $this->sector()->first();
            $name = $sector?->name;
            $sectorId = $this->target_sector_id;
            $q->where(function ($w) use ($name, $sectorId) {
                if ($name) {
                    $w->whereJsonContains('sectors', $name);
                }
                $w->orWhereHas('sectorsPivot', fn ($s) => $s->where('sectors.id', $sectorId));
            });
        } elseif ($this->target_type === 'by_country') {
            $country = $this->target_country;
            if ($country && strtoupper($country) !== 'ALL') {
                $q->where('primary_country', $country);
            }
        }

        return $q;
    }
}
