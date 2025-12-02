<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Game Platform API v1
|
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Routes
    |--------------------------------------------------------------------------
    */

    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('social/{provider}', [AuthController::class, 'socialLogin']);
    });

    // Public game info
    Route::get('games', [GameController::class, 'index']);
    Route::get('games/{slug}', [GameController::class, 'show']);
    Route::get('games/{slug}/leaderboard', [GameController::class, 'leaderboard']);
    Route::get('games/{slug}/challenges/{challengeId}/leaderboard', [GameController::class, 'challengeLeaderboard']);

    /*
    |--------------------------------------------------------------------------
    | Authenticated Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);

        // Player Profile
        Route::prefix('player')->group(function () {
            Route::get('/', [PlayerController::class, 'show']);
            Route::patch('/', [PlayerController::class, 'update']);
            Route::patch('preferences', [PlayerController::class, 'updatePreferences']);
            Route::get('achievements', [PlayerController::class, 'achievements']);
            Route::get('history', [PlayerController::class, 'history']);
            Route::get('streaks', [PlayerController::class, 'streaks']);
        });

        // Games & Challenges
        Route::prefix('games/{slug}')->group(function () {
            Route::get('daily', [GameController::class, 'daily']);
            Route::get('challenge/{number}', [GameController::class, 'challenge']);
        });

        // Game Sessions
        Route::prefix('sessions')->group(function () {
            Route::post('/', [SessionController::class, 'start']);
            Route::patch('{sessionId}', [SessionController::class, 'update']);
            Route::post('{sessionId}/complete', [SessionController::class, 'complete']);
            Route::post('{sessionId}/abandon', [SessionController::class, 'abandon']);
            Route::post('{sessionId}/hint', [SessionController::class, 'useHint']);
        });

        // Rewards
        Route::post('streak-freeze', [SessionController::class, 'useStreakFreeze']);
        Route::post('ad-watched', [SessionController::class, 'recordAdWatched']);

    });

    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    */

    Route::get('health', function () {
        return response()->json([
            'status' => 'healthy',
            'version' => config('gameplatform.version', '1.0.0'),
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->group(function () {
        Route::get('players', [AdminController::class, 'listPlayers']);
        Route::get('players/{id}', [AdminController::class, 'getPlayer']);
        Route::post('players/{id}/resources', [AdminController::class, 'updatePlayerResources']);
    });

});
