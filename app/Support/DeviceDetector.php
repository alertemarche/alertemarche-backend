<?php

namespace App\Support;

/**
 * Analyse d'un User-Agent en PHP pur (aucune dépendance externe).
 * Détermine le type d'appareil (ordinateur / téléphone / tablette),
 * le navigateur et la plateforme. Volontairement simple et robuste :
 * on privilégie une classification fiable des cas fréquents.
 */
class DeviceDetector
{
    /**
     * @return array{device_type:string,browser:string,platform:string}
     */
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;

        return [
            'device_type' => self::deviceType($ua),
            'browser'     => self::browser($ua),
            'platform'    => self::platform($ua),
        ];
    }

    /** desktop | mobile | tablet */
    public static function deviceType(string $ua): string
    {
        // Tablettes d'abord (certaines annoncent aussi "Mobile").
        if (preg_match('/iPad|Tablet|PlayBook|Nexus 7|Nexus 10|SM-T|Kindle|Silk/i', $ua)) {
            return 'tablet';
        }
        // Android sans "Mobile" = tablette Android.
        if (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false) {
            return 'tablet';
        }
        // Téléphones.
        if (preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|BB10|Opera Mini|IEMobile/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    public static function browser(string $ua): string
    {
        // L'ordre compte : Edge/Opera/Samsung avant Chrome, Chrome avant Safari.
        return match (true) {
            stripos($ua, 'Edg') !== false || stripos($ua, 'Edge') !== false => 'Edge',
            stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false => 'Opera',
            stripos($ua, 'SamsungBrowser') !== false                         => 'Samsung Internet',
            stripos($ua, 'Firefox') !== false || stripos($ua, 'FxiOS') !== false => 'Firefox',
            stripos($ua, 'CriOS') !== false                                  => 'Chrome',
            stripos($ua, 'Chrome') !== false || stripos($ua, 'Chromium') !== false => 'Chrome',
            stripos($ua, 'Safari') !== false                                 => 'Safari',
            stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false => 'Internet Explorer',
            $ua === ''                                                       => 'Inconnu',
            default                                                          => 'Autre',
        };
    }

    public static function platform(string $ua): string
    {
        return match (true) {
            stripos($ua, 'Windows') !== false                                => 'Windows',
            stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iPod') !== false => 'iOS',
            stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false => 'macOS',
            stripos($ua, 'Android') !== false                                => 'Android',
            stripos($ua, 'Linux') !== false                                  => 'Linux',
            default                                                          => 'Inconnu',
        };
    }
}
