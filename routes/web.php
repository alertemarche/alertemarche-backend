<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => 'AlerteMarché — API',
    'editor' => 'PRO BENIN SARL',
    'docs' => '/api/health',
]));
