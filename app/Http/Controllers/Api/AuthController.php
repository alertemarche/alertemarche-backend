<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\BrevoService;
use App\Services\GeolocationService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(
        protected BrevoService $brevo,
        protected WhatsAppService $whatsapp,
        protected GeolocationService $geo,
    ) {}

    /** Inscription multi-mode (email ou WhatsApp) + choix du profil. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone', 'required_without:email'],
            'password' => ['nullable', 'string', 'min:8'],
            'profile_type' => ['required', Rule::in(User::PROFILES)],
            'primary_country' => ['nullable', 'string', 'size:2'],
            'sectors' => ['nullable', 'array'],
            'sectors.*' => ['string', 'max:80'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:60'],
            'artisan_trade' => ['nullable', 'string', 'max:255'],
            'artisan_locality' => ['nullable', 'string', 'max:255'],
            'artisan_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            // Champs additionnels frontend (non stockés mais validés pour éviter rejets)
            'organization' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'password_confirmation' => ['nullable', 'string'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
        ]);

        $country = $data['primary_country'] ?? $this->geo->countryFromIp($request->ip());

        $user = User::create([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => isset($data['password']) ? Hash::make($data['password']) : null,
            'profile_type' => $data['profile_type'],
            'primary_country' => $country,
            'sectors' => $data['sectors'] ?? null,
            'keywords' => $data['keywords'] ?? null,
            'artisan_trade' => $data['artisan_trade'] ?? null,
            'artisan_locality' => $data['artisan_locality'] ?? null,
            'artisan_radius_km' => $data['artisan_radius_km'] ?? null,
            'notify_email' => $data['notify_email'] ?? true,
            'notify_whatsapp' => $data['notify_whatsapp'] ?? false,
        ]);

        // OTP de vérification
        $this->issueOtp($user);

        // Email de bienvenue (freemium)
        if ($user->email) {
            $this->brevo->sendWelcome($user->email, $user->name, $user->profile_type);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Inscription réussie. Activez un abonnement pour recevoir vos alertes.',
            'user' => $user,
            'token' => $token,
            'otp_required' => true,
        ], 201);
    }

    /** Génère et envoie un code OTP (e-mail prioritaire, sinon WhatsApp). */
    protected function issueOtp(User $user): void
    {
        // E-mail prioritaire (gratuit et fiable via Brevo) ; WhatsApp réservé au premium.
        $identifier = $user->email ?: $user->phone;
        $channel = $user->email ? 'email' : 'whatsapp';
        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'identifier' => $identifier,
            'channel' => $channel,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        $msg = "Votre code de vérification AlerteMarché est : {$code} (valable 10 minutes).";
        if ($channel === 'whatsapp') {
            $this->whatsapp->sendText($user->phone, $msg);
        } elseif ($user->email) {
            $this->brevo->sendAlert($user->email, $user->name, 'Code de vérification — AlerteMarché', $msg);
        }
    }

    /** Renvoie un OTP. */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate(['identifier' => ['required', 'string']]);
        $user = User::where('email', $request->identifier)->orWhere('phone', $request->identifier)->firstOrFail();
        $this->issueOtp($user);

        return response()->json(['message' => 'Nouveau code envoyé.']);
    }

    /** Vérification du code OTP. */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $otp = OtpCode::where('identifier', $data['identifier'])
            ->where('code', $data['code'])
            ->latest()
            ->first();

        if (! $otp || ! $otp->isValid()) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        $otp->update(['consumed_at' => now()]);

        $user = User::where('email', $data['identifier'])->orWhere('phone', $data['identifier'])->first();
        if ($user) {
            $user->forceFill([
                'email_verified_at' => $user->email ? now() : $user->email_verified_at,
                'phone_verified_at' => $user->phone ? now() : $user->phone_verified_at,
            ])->save();
        }

        return response()->json(['message' => 'Compte vérifié avec succès.', 'user' => $user]);
    }

    /** Connexion par email + mot de passe. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['identifier'])->orWhere('phone', $data['identifier'])->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'subscription' => $user->activeSubscription(),
            'free_alerts_remaining' => $user->freeAlertsRemaining(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }
}
