<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerAchievement;
use App\Services\ProgressTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlayerController extends Controller
{
    public function __construct(
        protected ProgressTracker $progressTracker
    ) {}

    /**
     * Get player profile and stats
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $player = $user->getOrCreatePlayer();

        $stats = $this->progressTracker->getPlayerStats($player);

        return response()->json([
            'success' => true,
            'data' => [
                'player' => [
                    'id' => $player->id,
                    'display_name' => $player->display_name,
                    'avatar_id' => $player->avatar_id,
                    'hints_balance' => $player->hints_balance,
                    'streak_freezes' => $player->streak_freezes,
                    'member_since' => $user->created_at->toIso8601String(),
                ],
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Update player profile
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'display_name' => 'nullable|string|max:50',
            'avatar_id' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $player = $request->user()->getOrCreatePlayer();

        $updates = array_filter([
            'display_name' => $request->display_name,
            'avatar_id' => $request->avatar_id,
        ], fn($v) => !is_null($v));

        if (!empty($updates)) {
            $player->update($updates);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'player' => [
                    'id' => $player->id,
                    'display_name' => $player->display_name,
                    'avatar_id' => $player->avatar_id,
                ],
            ],
        ]);
    }

    /**
     * Update player preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sound_enabled' => 'nullable|boolean',
            'haptics_enabled' => 'nullable|boolean',
            'dark_mode' => 'nullable|boolean',
            'notifications_enabled' => 'nullable|boolean',
            'push_token' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $player = $request->user()->getOrCreatePlayer();

        // Update direct fields
        if ($request->has('notifications_enabled')) {
            $player->notifications_enabled = $request->notifications_enabled;
        }
        if ($request->has('push_token')) {
            $player->push_token = $request->push_token;
        }

        // Update preferences JSON
        foreach (['sound_enabled', 'haptics_enabled', 'dark_mode'] as $pref) {
            if ($request->has($pref)) {
                $player->setPreference($pref, $request->$pref);
            }
        }

        $player->save();

        return response()->json([
            'success' => true,
            'data' => [
                'preferences' => $player->preferences,
                'notifications_enabled' => $player->notifications_enabled,
            ],
        ]);
    }

    /**
     * Get player achievements
     */
    public function achievements(Request $request): JsonResponse
    {
        $player = $request->user()->getOrCreatePlayer();
        $gameId = $request->query('game_id');

        $query = PlayerAchievement::where('player_id', $player->id)
            ->with('achievement');

        if ($gameId) {
            $query->where('game_id', $gameId);
        }

        $achievements = $query->orderByDesc('unlocked_at')->get();

        $formatted = $achievements->map(fn($pa) => [
            'id' => $pa->achievement->id,
            'slug' => $pa->achievement->slug,
            'name' => $pa->achievement->name,
            'description' => $pa->achievement->description,
            'icon' => $pa->achievement->icon,
            'category' => $pa->achievement->category,
            'points' => $pa->achievement->points,
            'unlocked_at' => $pa->unlocked_at->toIso8601String(),
            'game_id' => $pa->game_id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'achievements' => $formatted,
                'total_points' => PlayerAchievement::getPlayerPoints($player->id),
                'count' => $achievements->count(),
            ],
        ]);
    }

    /**
     * Get player's game history
     */
    public function history(Request $request): JsonResponse
    {
        $player = $request->user()->getOrCreatePlayer();
        $gameId = $request->query('game_id');
        $limit = min((int)$request->query('limit', 20), 100);

        $query = $player->sessions()
            ->with(['game:id,slug,name', 'challenge:id,challenge_number,challenge_date'])
            ->where('status', 'completed')
            ->orderByDesc('completed_at');

        if ($gameId) {
            $query->where('game_id', $gameId);
        }

        $sessions = $query->limit($limit)->get();

        $formatted = $sessions->map(fn($s) => [
            'id' => $s->id,
            'game' => $s->game ? [
                'id' => $s->game->id,
                'slug' => $s->game->slug,
                'name' => $s->game->name,
            ] : null,
            'challenge_number' => $s->challenge?->challenge_number,
            'session_type' => $s->session_type,
            'score' => $s->score,
            'duration' => $s->getDurationFormatted(),
            'duration_seconds' => $s->duration_seconds,
            'hints_used' => $s->hints_used,
            'completed_at' => $s->completed_at->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => $formatted,
            ],
        ]);
    }

    /**
     * Get player's streaks across all games
     */
    public function streaks(Request $request): JsonResponse
    {
        $player = $request->user()->getOrCreatePlayer();

        $streaks = $player->streaks()->with('game:id,slug,name')->get();

        $formatted = $streaks->map(function ($streak) {
            $status = $streak->checkStatus();
            return [
                'game' => [
                    'id' => $streak->game->id,
                    'slug' => $streak->game->slug,
                    'name' => $streak->game->name,
                ],
                'current_streak' => $streak->current_streak,
                'longest_streak' => $streak->longest_streak,
                'status' => $status['status'],
                'last_completed' => $streak->last_completed_date?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'streaks' => $formatted,
                'freezes_available' => $player->streak_freezes,
            ],
        ]);
    }
}
