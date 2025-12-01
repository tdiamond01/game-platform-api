<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
            'timezone' => 'nullable|string|timezone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'timezone' => $request->timezone ?? config('gameplatform.daily.timezone'),
        ]);

        // Create player profile
        $player = $user->getOrCreatePlayer();

        // Create API token
        $token = $user->createToken('game-platform')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'player' => [
                    'id' => $player->id,
                    'display_name' => $player->display_name,
                    'hints_balance' => $player->hints_balance,
                    'streak_freezes' => $player->streak_freezes,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Login with email and password
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Update last active
        $user->touchLastActive();

        // Get or create player
        $player = $user->getOrCreatePlayer();

        // Create token
        $deviceName = $request->device_name ?? 'game-platform';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'player' => [
                    'id' => $player->id,
                    'display_name' => $player->display_name,
                    'hints_balance' => $player->hints_balance,
                    'streak_freezes' => $player->streak_freezes,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Social login (Apple, Google)
     */
    public function socialLogin(Request $request, string $provider): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider_token' => 'required|string',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate provider
        if (!in_array($provider, ['apple', 'google'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid provider',
            ], 400);
        }

        // In production, verify the token with the provider
        // For now, we'll trust the client-provided data
        // TODO: Implement actual OAuth verification

        $providerId = hash('sha256', $request->provider_token);

        // Find existing user or create new one
        $user = User::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if (!$user) {
            // Check if email already exists
            if ($request->email) {
                $existingUser = User::where('email', $request->email)->first();
                if ($existingUser) {
                    // Link provider to existing account
                    $existingUser->update([
                        'provider' => $provider,
                        'provider_id' => $providerId,
                    ]);
                    $user = $existingUser;
                }
            }

            if (!$user) {
                $user = User::create([
                    'name' => $request->name ?? 'Player',
                    'email' => $request->email,
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'email_verified_at' => now(),
                ]);
            }
        }

        $user->touchLastActive();
        $player = $user->getOrCreatePlayer();
        $token = $user->createToken("{$provider}-login")->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'player' => [
                    'id' => $player->id,
                    'display_name' => $player->display_name,
                    'hints_balance' => $player->hints_balance,
                    'streak_freezes' => $player->streak_freezes,
                ],
                'token' => $token,
                'is_new_user' => $user->wasRecentlyCreated,
            ],
        ]);
    }

    /**
     * Logout (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->touchLastActive();
        $player = $user->getOrCreatePlayer();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'timezone' => $user->getTimezone(),
                    'created_at' => $user->created_at->toIso8601String(),
                ],
                'player' => [
                    'id' => $player->id,
                    'display_name' => $player->display_name,
                    'avatar_id' => $player->avatar_id,
                    'hints_balance' => $player->hints_balance,
                    'streak_freezes' => $player->streak_freezes,
                    'total_games_played' => $player->total_games_played,
                    'notifications_enabled' => $player->notifications_enabled,
                    'preferences' => $player->preferences,
                ],
            ],
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Delete current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('game-platform-refresh')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
            ],
        ]);
    }
}
