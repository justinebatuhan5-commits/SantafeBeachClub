<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccommodationController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\ChatbotController;

/*
|--------------------------------------------------------------------------
| Santa Fe Beach Club API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // -------------------------------------------------------------
    // Public Endpoints (Rate Limited)
    // -------------------------------------------------------------
    Route::middleware('throttle:60,1')->group(function () {
        // Health check
        Route::get('/ping', function () {
            return response()->json([
                'status'    => 'ok',
                'system'    => 'Santa Fe Beach Accommodation System (Laravel Core)',
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        // Public accommodations browse & availability check
        Route::get('/accommodations', [AccommodationController::class, 'index']);
        Route::post('/accommodations/check-availability', [AccommodationController::class, 'checkAvailability']);

        // Public price estimation & guest reservation submission
        Route::post('/reservations/calculate', [ReservationController::class, 'calculate']);
        Route::post('/reservations', [ReservationController::class, 'store']);

        // Auth Login
        Route::post('/auth/login', [AuthController::class, 'login']);

        // Phase 7: Multilingual AI Chatbot (Gemini API with rate limiting)
        Route::post('/chatbot/message', [ChatbotController::class, 'message']);
    });

    // -------------------------------------------------------------
    // Authenticated / Protected Operations (Sanctum & Service Bridge)
    // -------------------------------------------------------------
    Route::middleware(['service.bridge'])->group(function () {
        // Auth management
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Receptionist & Admin operations
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/reservations/{id}/confirm', [ReservationController::class, 'confirm']);
        Route::post('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);
        Route::post('/reservations/{id}/checkin', [ReservationController::class, 'checkIn']);
        Route::post('/reservations/{id}/checkout', [ReservationController::class, 'checkOut']);

        // Accommodation unit status management
        Route::post('/accommodations/maintenance', [AccommodationController::class, 'setMaintenance']);

        // Automation Management & Scheduled Operations (Restricted)
        Route::get('/automation', [AutomationController::class, 'index']);
        Route::post('/automation/trigger', [AutomationController::class, 'trigger']);
    });
});
