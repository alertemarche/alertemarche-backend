<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BrevoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gère les demandes de réservation d'espaces publicitaires.
 * Reçoit le mini-form pub depuis le frontend, envoie un email à info@alertemarche.com.
 */
class PubInquiryController extends Controller
{
    protected BrevoService $brevo;

    public function __construct(BrevoService $brevo)
    {
        $this->brevo = $brevo;
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company'  => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:200'],
            'country'  => ['nullable', Rule::in(['BJ', 'TG', 'CI', 'SN', 'BF', 'all'])],
            'message'  => ['nullable', 'string', 'max:1000'],
        ]);

        $countryLabel = match ($data['country'] ?? 'all') {
            'BJ'    => '🇧🇯 Bénin',
            'TG'    => '🇹🇬 Togo',
            'CI'    => '🇨🇮 Côte d\'Ivoire',
            'SN'    => '🇸🇳 Sénégal',
            'BF'    => '🇧🇫 Burkina Faso',
            default => '🌍 Tous les 5 pays',
        };

        $html = view('emails.pub_inquiry', [
            'company'      => $data['company'],
            'email'        => $data['email'],
            'countryLabel' => $countryLabel,
            'message'      => $data['message'] ?? null,
        ])->render();

        $this->brevo->send(
            toEmail: 'info@alertemarche.com',
            toName:  'AlerteMarché – Pub',
            subject: "📢 Demande espace publicitaire : {$data['company']}",
            htmlContent: $html,
        );

        return response()->json([
            'success' => true,
            'message' => 'Votre demande a bien été envoyée. Nous vous répondrons sous 24 h.',
        ], 201);
    }
}
