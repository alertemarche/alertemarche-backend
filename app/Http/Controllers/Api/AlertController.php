<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /** Historique chronologique des alertes reçues par l'abonné. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->alerts()->latest()->paginate(20)
        );
    }
}
