<?php

namespace App\Http\Middleware;

use App\Models\DeviceActivity;
use App\Support\DeviceDetector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enregistre l'activité de l'appareil pour chaque requête API d'un
 * utilisateur connecté. Une empreinte (hash user_id + user-agent)
 * identifie l'appareil ; on met simplement à jour `last_seen_at`.
 *
 * Un petit verrou de cache (60 s par empreinte) évite d'écrire en
 * base à chaque requête (une même page déclenche plusieurs appels API).
 * À placer APRÈS l'authentification Sanctum pour disposer de l'utilisateur.
 */
class TrackDeviceActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        // On laisse d'abord passer la requête (l'auth se résout dans le pipeline).
        $response = $next($request);

        try {
            $user = $request->user();
            if ($user) {
                $this->record($request, $user->id);
            }
        } catch (\Throwable $e) {
            // Le suivi ne doit jamais casser une requête applicative.
        }

        return $response;
    }

    protected function record(Request $request, int $userId): void
    {
        $ua = (string) $request->userAgent();
        $fingerprint = hash('sha256', $userId . '|' . $ua);

        // Throttle : au plus une écriture par minute et par appareil.
        if (! Cache::add('devtrack:' . $fingerprint, 1, 60)) {
            return;
        }

        $info = DeviceDetector::parse($ua);

        DeviceActivity::updateOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'user_id'      => $userId,
                'device_type'  => $info['device_type'],
                'browser'      => $info['browser'],
                'platform'     => $info['platform'],
                'user_agent'   => mb_substr($ua, 0, 1000),
                'ip'           => $request->ip(),
                'last_seen_at' => now(),
            ]
        );
    }
}
