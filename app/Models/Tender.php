<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tender extends Model
{
    protected $fillable = [
        'title', 'title_fr', 'teaser_title', 'institution', 'reference', 'location', 'estimated_amount',
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

    /**
     * `teaser_title` est un champ interne (titre masqué pour non-abonnés) : le
     * paywall l'injecte dans `title`/`title_fr` au besoin. On ne l'expose jamais
     * tel quel dans les réponses JSON pour éviter toute confusion côté front.
     */
    protected $hidden = ['teaser_title', 'dedup_hash'];

    /**
     * Génère le « titre teaser » d'un avis pour les visiteurs non abonnés.
     *
     * Objectif : conserver l'OBJET du marché (utile au référencement et à la
     * compréhension) tout en masquant l'identité de l'acheteur — qui, dans les
     * intitulés réels, se trouve quasi systématiquement EN FIN de titre sous
     * plusieurs formes :
     *   • code projet après un séparateur «  - PARAE », «  - PA-PAPC »
     *   • connecteur : « au profit de … », « au sein d'… », « pour le compte de … »
     *   • acheteur en fin : « … de l'APB », « … du CHD Zou », « … de la Mairie de X »
     *
     * On coupe la partie acheteur puis on plafonne le nombre de mots pour
     * obtenir un teaser homogène terminé par « … ». Le titre complet reste
     * intact en base : ce masquage n'est appliqué qu'à l'affichage verrouillé.
     */
    public static function teaserTitle(?string $title, int $maxWords = 9): string
    {
        $title = (string) $title;
        if (trim($title) === '') {
            return $title;
        }

        $t = trim((string) preg_replace('/\s+/u', ' ', $title));
        // Trim multi-octets : trim() natif coupe octet par octet et casserait
        // un caractère accentué en bordure (ex. « À » = 0xC3 0x80).
        $t = (string) preg_replace('/^[\s«»"\'‘’“”]+|[\s«»"\'‘’“”]+$/u', '', $t);
        $original = $t;

        // 1. Retire le préfixe de bruit «  <TYPE> - <Pays> - » (AMI/AAO/AOI/DRP…).
        $t = (string) preg_replace('/^(AAON|AAOI|AAO|AMI|AOI|AON|AOO|DRPO|DRP|AAC|AO)\b[\s\-–—:]*/iu', '', $t);
        $t = (string) preg_replace('/^(b[ée]nin|togo|c[ôo]te d[\'’]ivoire|s[ée]n[ée]gal)\b[\s\-–—:]*/iu', '', $t);

        // 2. Coupe un code projet / acheteur en fin après un séparateur «  - CODE ».
        if (preg_match('/\s[-–—]\s+([^-–—]{1,60})$/u', $t, $m, PREG_OFFSET_CAPTURE)) {
            $tail = trim($m[1][0]);
            $tailWords = preg_split('/\s+/u', $tail, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($tailWords) <= 6) {
                $t = trim(substr($t, 0, $m[0][1]));
            }
        }

        // 3. Coupe les clauses connecteur qui introduisent l'acheteur.
        $t = (string) preg_replace(
            '/\s+(au profit d|pour le compte d|au b[ée]n[ée]fice d|en faveur d|pour le minist[èe]re|au sein d|de la part d|au nom d|pour l[\'’]acquisition au profit)\b.*$/iu',
            '',
            $t
        );

        // 4. Coupe l'acheteur en fin : « … du/de la/de l'/des <Nom propre / ACRONYME> ».
        $t = trim((string) preg_replace(
            '/\s+(du|de la|de l[\'’]|des|de)\s+(l[\'’])?([A-ZÉÈÊÀ][\wÀ-ÿ\'’.\-]*(?:\s+[A-ZÉÈÊÀ0-9][\wÀ-ÿ\'’.\-]*){0,3})\s*$/u',
            ' ',
            $t
        ));

        // 5. Plafond de mots — masque la fin de manière homogène (« … »).
        $words = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $truncated = count($words) > $maxWords;
        if ($truncated) {
            $words = array_slice($words, 0, $maxWords);
        }
        $t = (string) preg_replace('/^[\s,;:\-–—«»]+|[\s,;:\-–—«»]+$/u', '', implode(' ', $words));

        if (($truncated || $t !== $original) && $t !== '') {
            $t .= ' …';
        }

        return preg_replace('/^[\s…]+|[\s…]+$/u', '', $t) !== '' ? $t : $original;
    }
}
