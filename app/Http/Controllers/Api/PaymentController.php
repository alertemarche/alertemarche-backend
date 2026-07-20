<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Intégration KKPays (Mobile Money + carte) — webhook de confirmation.
 * Placeholder : à finaliser en Semaine 7 avec les clés marchand.
 */
class PaymentController extends Controller
{
    /** Webhook de confirmation de paiement KKPays. */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('KKPays webhook reçu', $request->all());

        $reference = $request->input('reference');
        $status = $request->input('status');

        if ($reference && $status === 'success') {
            $subscription = Subscription::where('payment_reference', $reference)->first();
            if ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'started_at' => now(),
                    'expires_at' => now()->addMonth(),
                ]);
                $subscription->user->update(['notify_whatsapp' => true, 'is_suspended' => false]);
            }
        }

        return response()->json(['received' => true]);
    }
}
