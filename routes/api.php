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

// Appels d'offres (public)
Route::get('/tenders', [TenderController::class, 'index']);
Route::get('/tenders/{tender}', [TenderController::class, 'show']);

// Besoins artisans (public : lecture)
Route::get('/needs', [ArtisanNeedController::class, 'index']);

// Authentification
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/otp/resend', [AuthController::class, 'resendOtp']);
});

// Paiement — webhook KKPays (public, signé côté KKPays)
Route::post('/payments/kkpays/webhook', [PaymentController::class, 'webhook']);

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
