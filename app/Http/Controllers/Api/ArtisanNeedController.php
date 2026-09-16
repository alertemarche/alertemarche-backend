<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtisanNeed;
use App\Services\KkiapayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ArtisanNeedController extends Controller
{
    /** Liste publique des besoins approuvés — les annonces PREMIUM d'abord. */
    public function index(Request $request): JsonResponse
    {
        $query = ArtisanNeed::query()
            ->where('status', 'approved')
            ->orderByRaw('is_premium DESC, created_at DESC');

        if ($request->filled('country')) {
            $query->where('country', $request->string('country'));
        }
        if ($request->filled('trade')) {
            $query->where('trade', 'ilike', '%'.$request->string('trade').'%');
        }
        if ($request->filled('is_premium')) {
            $query->where('is_premium', filter_var($request->input('is_premium'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min($perPage, 50));

        return response()->json($query->paginate($perPage));
    }

    /** Publication d'un besoin (entreprises, admin, ONG) — validation éditoriale requise. */
    public function store(Request $request, KkiapayService $kkiapay): JsonResponse
    {
        $data = $request->validate([
            'trade' => ['required', 'string', 'max:255'],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'people_needed' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'locality' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['required', Rule::in(['BJ', 'TG', 'CI', 'SN', 'BF'])],
            'estimated_budget' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'contact' => ['required', 'string', 'max:255'],
            'wants_premium' => ['nullable', 'boolean'],
            // Images : optionnelles, PREMIUM uniquement, JPG/PNG/WEBP ≤ 2 Mo
            'image1' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image2' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $wantsPremium = (bool) ($data['wants_premium'] ?? false);

        // Retirer les champs non-scalaires avant le create()
        unset($data['wants_premium'], $data['image1'], $data['image2']);

        // Stocker les images (seulement si PREMIUM demandé)
        $image1Url = null;
        $image2Url = null;
        if ($wantsPremium) {
            if ($request->hasFile('image1') && $request->file('image1')->isValid()) {
                $path = $request->file('image1')->store('needs', 'public');
                $image1Url = Storage::url($path);
            }
            if ($request->hasFile('image2') && $request->file('image2')->isValid()) {
                $path = $request->file('image2')->store('needs', 'public');
                $image2Url = Storage::url($path);
            }
        }

        // Nouvelle logique : l'utilisateur soumet GRATUITEMENT même s'il veut PREMIUM
        // Le paiement sera demandé APRÈS validation manuelle par l'admin
        $need = ArtisanNeed::create([
            ...$data,
            'publisher_id' => $request->user()?->id,
            'status' => 'pending', // en attente de validation éditoriale
            'wants_premium' => $wantsPremium,
            'is_premium' => false, // pas encore premium tant que non payé
            'paid_at' => null,
            'expires_at' => null, // sera défini après validation + paiement
            'image1' => $image1Url,
            'image2' => $image2Url,
        ]);

        $message = $wantsPremium
            ? 'Annonce soumise avec demande PREMIUM ! Notre équipe la validera sous 24-48h, puis vous recevrez un lien pour régler 50 000 FCFA et activer la formule PREMIUM (30 jours, mise en avant).'
            : 'Annonce gratuite soumise ! Elle sera validée et diffusée sous 24-48h (visible 15 jours).';

        return response()->json(['message' => $message, 'need' => $need], 201);
    }

    /** Vérification a posteriori du paiement PREMIUM (widget KKiaPay). */
    public function verifyPremium(Request $request, ArtisanNeed $need, KkiapayService $kkiapay): JsonResponse
    {
        $request->validate([
            'transaction_id' => ['required', 'string', 'max:255'],
        ]);

        abort_unless($need->publisher_id === $request->user()?->id, 403);

        $result = $kkiapay->verifyTransaction((string) $request->input('transaction_id'));

        if (! $result['success']) {
            return response()->json([
                'message' => "Le paiement n'a pas pu être confirmé.",
                'status' => $result['status'],
            ], 422);
        }

        $need->update([
            'is_premium' => true,
            'paid_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'message' => 'Paiement confirmé. Votre annonce est désormais PREMIUM (30 jours).',
            'need' => $need->fresh(),
        ]);
    }

    /** Suivi : nombre d'artisans alertés pour un besoin publié. */
    public function responses(Request $request, ArtisanNeed $need): JsonResponse
    {
        abort_unless($need->publisher_id === $request->user()?->id, 403);

        $count = \App\Models\Alert::where('source_type', 'artisan_need')
            ->where('source_id', $need->id)->count();

        return response()->json(['need_id' => $need->id, 'artisans_alerted' => $count]);
    }

    /** [ADMIN] Envoyer l'email de demande de paiement PREMIUM après validation. */
    public function requestPremiumPayment(Request $request, ArtisanNeed $need): JsonResponse
    {
        // Vérifier que l'utilisateur est admin
        abort_unless($request->user()?->role === 'admin', 403, 'Action réservée aux administrateurs.');

        // Vérifier que l'annonce veut premium et n'est pas déjà premium
        abort_unless($need->wants_premium && !$need->is_premium, 422, 'Cette annonce ne demande pas PREMIUM ou est déjà PREMIUM.');

        // Générer un token de paiement unique
        $paymentToken = \Illuminate\Support\Str::random(32);
        $need->update(['payment_token' => $paymentToken]);

        // Envoyer l'email via BrevoService
        $publisher = $need->publisher;
        if (!$publisher || !$publisher->email) {
            return response()->json(['message' => 'Éditeur introuvable ou sans email.'], 422);
        }

        $paymentUrl = "https://www.alertemarche.com/paiement-annonce.html?token={$paymentToken}";
        
        $emailBody = "
            <h2 style='color:#d97706;'>✅ Votre annonce est validée !</h2>
            <p>Bonjour <strong>{$publisher->name}</strong>,</p>
            <p>Bonne nouvelle ! Votre annonce <strong>« {$need->trade} »</strong> a été validée par notre équipe.</p>
            
            <div style='background:#fffbeb;border:2px solid #f59e0b;border-radius:10px;padding:20px;margin:20px 0;'>
                <h3 style='margin:0 0 10px;color:#92400e;'>⭐ Activation PREMIUM</h3>
                <p style='margin:0;'>Vous avez demandé la formule <strong>PREMIUM</strong> pour bénéficier de :</p>
                <ul style='margin:10px 0;padding-left:20px;'>
                    <li>✨ <strong>Badge PREMIUM doré</strong> en haut de page</li>
                    <li>📅 <strong>30 jours de visibilité</strong> (au lieu de 15)</li>
                    <li>🔥 <strong>Insertion dans le fil des marchés publics/privés</strong></li>
                    <li>📈 <strong>Jusqu'à 3× plus de vues</strong></li>
                </ul>
                <p style='margin:10px 0 0;'><strong>Tarif :</strong> 50 000 FCFA (paiement unique, sécurisé via Mobile Money)</p>
            </div>

            <div style='text-align:center;margin:30px 0;'>
                <a href='{$paymentUrl}' style='display:inline-block;background:#f59e0b;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:1.1rem;'>
                    💳 Payer 50 000 FCFA et activer PREMIUM
                </a>
            </div>

            <p style='font-size:0.9rem;color:#6b7280;'>
                ⏱️ Ce lien est valable 7 jours. Après paiement, votre annonce sera immédiatement mise en avant.<br>
                ❌ Si vous ne souhaitez plus la formule PREMIUM, votre annonce sera publiée gratuitement (15 jours) automatiquement.
            </p>
            
            <p>Cordialement,<br><strong>L'équipe AlerteMarché</strong></p>
        ";

        try {
            $brevo = app(\App\Services\BrevoService::class);
            $brevo->sendAlert(
                $publisher->email,
                $publisher->name,
                '✅ Votre annonce est validée — Paiement PREMIUM disponible',
                $emailBody
            );

            return response()->json([
                'message' => 'Email de demande de paiement envoyé avec succès.',
                'payment_url' => $paymentUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage()], 500);
        }
    }
}
