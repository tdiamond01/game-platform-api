<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Update player resources (hints, streak freezes).
     *
     * POST /api/v1/admin/players/{id}/resources
     */
    public function updatePlayerResources(Request $request, int $id): JsonResponse
    {
        // Validate admin token
        $adminToken = $request->header('X-Admin-Token');
        if ($adminToken !== config('gameplatform.admin_token')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $player = Player::find($id);

        if (!$player) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found',
            ], 404);
        }

        $validated = $request->validate([
            'hints_balance' => 'sometimes|integer|min:0',
            'streak_freezes' => 'sometimes|integer|min:0',
        ]);

        $before = [
            'hints_balance' => $player->hints_balance,
            'streak_freezes' => $player->streak_freezes,
        ];

        if (isset($validated['hints_balance'])) {
            $player->hints_balance = $validated['hints_balance'];
        }

        if (isset($validated['streak_freezes'])) {
            $player->streak_freezes = $validated['streak_freezes'];
        }

        $player->save();

        return response()->json([
            'success' => true,
            'data' => [
                'player_id' => $player->id,
                'display_name' => $player->display_name,
                'before' => $before,
                'after' => [
                    'hints_balance' => $player->hints_balance,
                    'streak_freezes' => $player->streak_freezes,
                ],
            ],
        ]);
    }

    /**
     * Get player details.
     *
     * GET /api/v1/admin/players/{id}
     */
    public function getPlayer(Request $request, int $id): JsonResponse
    {
        // Validate admin token
        $adminToken = $request->header('X-Admin-Token');
        if ($adminToken !== config('gameplatform.admin_token')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $player = Player::with('user')->find($id);

        if (!$player) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'player' => [
                    'id' => $player->id,
                    'display_name' => $player->display_name,
                    'hints_balance' => $player->hints_balance,
                    'streak_freezes' => $player->streak_freezes,
                    'total_games_played' => $player->total_games_played,
                    'created_at' => $player->created_at,
                ],
                'user' => $player->user ? [
                    'id' => $player->user->id,
                    'name' => $player->user->name,
                    'email' => $player->user->email,
                ] : null,
            ],
        ]);
    }

    /**
     * List all players.
     *
     * GET /api/v1/admin/players
     */
    public function listPlayers(Request $request): JsonResponse
    {
        // Validate admin token
        $adminToken = $request->header('X-Admin-Token');
        if ($adminToken !== config('gameplatform.admin_token')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $players = Player::with('user')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn($player) => [
                'id' => $player->id,
                'display_name' => $player->display_name,
                'email' => $player->user?->email,
                'hints_balance' => $player->hints_balance,
                'streak_freezes' => $player->streak_freezes,
                'total_games_played' => $player->total_games_played,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'players' => $players,
                'count' => $players->count(),
            ],
        ]);
    }
}
