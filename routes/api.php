<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ArtisanNeedController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GeoController;
use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TenderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API AlerteMarché
|--------------------------------------------------------------------------
*/

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

// Paiement — KKiaPay
Route::get('/payments/kkiapay/config', [PaymentController::class, 'config']);   // clé publique widget
Route::post('/payments/kkiapay/webhook', [PaymentController::class, 'webhook']); // serveur-à-serveur (public)

// Ingestion scrapers (jeton dédié)
Route::middleware('scraper')->prefix('ingest')->group(function () {
    Route::post('/tenders', [IngestController::class, 'tenders']);
    Route::post('/log', [IngestController::class, 'log']);
});

// Espace abonné (authentifié)
Route::middleware('auth:sanctum')->group(function () {
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

    // Back-office
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/scrapers', [AdminController::class, 'scrapers']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/needs/pending', [AdminController::class, 'pendingNeeds']);
        Route::post('/needs/{need}/validate', [AdminController::class, 'validateNeed']);
    });
});
