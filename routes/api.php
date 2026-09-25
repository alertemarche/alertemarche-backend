<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\NewsletterAmController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ArtisanNeedController;
use App\Http\Controllers\Api\PubInquiryController;
use App\Http\Controllers\Api\JsErrorController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GeoController;
use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TenderController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API AlerteMarché
|--------------------------------------------------------------------------
*/

// Statistiques publiques
Route::get('/stats/public', [StatsController::class, 'public']);

// Santé
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'alertemarche-backend',
    'time' => now()->toIso8601String(),
]));

// Géolocalisation
Route::get('/geo/detect', [GeoController::class, 'detect']);

// Tarification (public)
Route::get('/pricing/grid', [PricingController::class, 'grid']);
Route::post('/pricing/quote', [PricingController::class, 'quote']);

// Formules d'abonnement par durée (public) — source de vérité = config/plans.php
Route::get('/plans', fn () => response()->json([
    'currency' => config('plans.currency', 'XOF'),
    'plans' => config('plans.plans', []),
]));

// Secteurs & pays de référence (public)
// Source de vérité unique = config/sectors.php (21 secteurs data-réels du Bénin).
Route::get('/sectors', fn () => response()->json(
    \App\Support\SectorClassifier::options()
));
Route::get('/countries', fn () => response()->json(
    \App\Models\Country::query()->where('active', true)->get(['code', 'name', 'flag_emoji', 'currency'])
));

// Appels d'offres (public)
Route::get('/tenders', [TenderController::class, 'index']);
Route::get('/tenders/{tender}', [TenderController::class, 'show']);

// Espaces publicitaires — demande de réservation (public)
Route::post('/pub-inquiry', [PubInquiryController::class, 'store']);

// Logging des erreurs JavaScript frontend (monitoring)
Route::post('/log-js-error', [JsErrorController::class, 'log']);

// Besoins artisans (public : lecture + expression d'un besoin par un visiteur)
Route::get('/needs', [ArtisanNeedController::class, 'index']);
Route::post('/needs/express', [ArtisanNeedController::class, 'store']);

// Authentification
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/otp/resend', [AuthController::class, 'resendOtp']);
});

// Connexion admin autonome (route publique — pas de middleware auth)
Route::post('/admin/login', [AdminController::class, 'adminLogin']);

// Paiement — KKiaPay
Route::get('/payments/kkiapay/config', [PaymentController::class, 'config']);   // clé publique widget
Route::post('/payments/kkiapay/webhook', [PaymentController::class, 'webhook']); // serveur-à-serveur (public)
Route::get('/payments/need-by-token', [PaymentController::class, 'getNeedByToken']); // récupérer annonce par token
Route::post('/payments/activate-premium-by-token', [PaymentController::class, 'activatePremiumByToken']); // activer premium après paiement

// Ingestion scrapers (jeton dédié)
Route::middleware('scraper')->prefix('ingest')->group(function () {
    Route::post('/tenders', [IngestController::class, 'tenders']);
    Route::post('/log', [IngestController::class, 'log']);
});

// Espace abonné (authentifié)
Route::middleware(['auth:sanctum', 'track.device'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/alerts', [AlertController::class, 'index']);

    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::post('/subscriptions/{subscription}/activate', [SubscriptionController::class, 'activate']);

    // Vérification serveur du paiement KKiaPay (après succès du widget)
    Route::post('/payments/kkiapay/verify', [PaymentController::class, 'verify']);

    // Publication de besoins (entreprises, admin, ONG)
    Route::post('/needs', [ArtisanNeedController::class, 'store']);
    Route::get('/needs/{need}/responses', [ArtisanNeedController::class, 'responses']);
    Route::post('/needs/{need}/verify-premium', [ArtisanNeedController::class, 'verifyPremium']);
    
    // [ADMIN] Demande de paiement PREMIUM après validation
    Route::post('/needs/{need}/request-premium-payment', [ArtisanNeedController::class, 'requestPremiumPayment'])->middleware('admin');

    // Back-office
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/alerts-stats', [AdminController::class, 'alertsStats']);
        Route::get('/scrapers', [AdminController::class, 'scrapers']);
        Route::get('/activity-calendar', [AdminController::class, 'activityCalendar']);
        Route::get('/device-stats', [AdminController::class, 'deviceStats']);
        // Utilisateurs
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'showUser']);
        Route::post('/users', [AdminController::class, 'createUserManual']);
        Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
        Route::patch('/users/{user}/suspend', [AdminController::class, 'toggleSuspend']);
        // Abonnements
        Route::get('/subscriptions', [AdminController::class, 'subscriptions']);
        Route::post('/subscriptions/grant', [AdminController::class, 'grantSubscription']);
        Route::delete('/subscriptions/{subscription}', [AdminController::class, 'deleteSubscription']);
        // Paiements
        Route::get('/payments', [AdminController::class, 'payments']);
        // Annonces
        Route::get('/needs/pending', [AdminController::class, 'pendingNeeds']);
        Route::post('/needs/{need}/validate', [AdminController::class, 'validateNeed']);

        // Marchés manuels (saisie assistée par IA depuis une photo)
        Route::post('/tenders/extract-image', [AdminController::class, 'extractFromImage']);
        Route::get('/tenders', [AdminController::class, 'listManualTenders']);
        Route::post('/tenders', [AdminController::class, 'createManualTender']);
        Route::patch('/tenders/{id}', [AdminController::class, 'updateManualTender']);
        Route::delete('/tenders/{id}', [AdminController::class, 'deleteManualTender']);

        // Secteurs (référentiel + nombre d'abonnés par secteur)
        Route::get('/sectors', [NewsletterAmController::class, 'sectors']);
        Route::post('/sectors/assign', [NewsletterAmController::class, 'assignSector']);

        // Newsletters & Annonces publicitaires
        // /target-count doit précéder les routes à paramètre {newsletter}.
        Route::get('/newsletters/target-count', [NewsletterAmController::class, 'getTargetCount']);
        Route::get('/newsletters', [NewsletterAmController::class, 'index']);
        Route::post('/newsletters', [NewsletterAmController::class, 'create']);
        Route::get('/newsletters/{newsletter}/preview', [NewsletterAmController::class, 'preview']);
        Route::post('/newsletters/{newsletter}/send', [NewsletterAmController::class, 'send']);
        Route::delete('/newsletters/{newsletter}', [NewsletterAmController::class, 'destroy']);
    });
});
