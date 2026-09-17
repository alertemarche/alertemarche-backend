<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

/**
 * Contrôleur pour recevoir et logger les erreurs JavaScript frontend
 * 
 * Les erreurs sont capturées par error-protection.js et envoyées ici
 * pour analyse et monitoring.
 */
class JsErrorController extends Controller
{
    /**
     * Enregistre une erreur JavaScript côté frontend
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function log(Request $request): JsonResponse
    {
        try {
            $error = $request->input('error', []);
            $type = $request->input('type', 'unknown');
            
            // Validation basique
            if (empty($error) || !isset($error['message'])) {
                return response()->json(['status' => 'ignored'], 400);
            }
            
            // Préparer le contexte pour le log
            $context = [
                'type' => $type,
                'message' => $error['message'] ?? 'Unknown error',
                'url' => $error['url'] ?? $request->header('Referer'),
                'user_agent' => $error['userAgent'] ?? $request->header('User-Agent'),
                'filename' => $error['filename'] ?? 'unknown',
                'line' => $error['line'] ?? 0,
                'column' => $error['column'] ?? 0,
                'stack' => $error['stack'] ?? 'No stack trace',
                'timestamp' => $error['timestamp'] ?? now()->toISOString(),
                'ip' => $request->ip(),
            ];
            
            // Logger dans le canal approprié selon la gravité
            if ($type === 'uncaught' || $type === 'tdz') {
                Log::error('[JS Frontend Error]', $context);
            } else {
                Log::warning('[JS Frontend Warning]', $context);
            }
            
            // Optionnel : stocker en base pour un dashboard (à implémenter)
            // JsError::create($context);
            
            return response()->json(['status' => 'logged'], 200);
            
        } catch (\Exception $e) {
            // Ne pas faire planter l'API si le logging échoue
            Log::error('[JsErrorController] Failed to log JS error', [
                'exception' => $e->getMessage(),
            ]);
            
            return response()->json(['status' => 'error'], 500);
        }
    }
}
