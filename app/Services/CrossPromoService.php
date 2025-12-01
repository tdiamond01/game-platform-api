<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CrossPromoService
{
    protected bool $enabled;
    protected int $frequency;
    protected ?string $waitPulseUrl;
    protected ?string $waitPulseKey;

    public function __construct()
    {
        $this->enabled = config('gameplatform.crosspromo.enabled', true);
        $this->frequency = config('gameplatform.crosspromo.frequency', 5);
        $this->waitPulseUrl = config('gameplatform.crosspromo.waitpulse.api_url');
        $this->waitPulseKey = config('gameplatform.crosspromo.waitpulse.api_key');
    }

    /**
     * Get cross-promotion recommendation for a player
     */
    public function getRecommendation(Player $player, ?string $currentGameSlug = null): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        // Check frequency (only show 1 in N times)
        if (!$this->shouldShowPromo($player->id)) {
            return null;
        }

        // Get games the player hasn't tried or plays less
        $recommendation = $this->findBestGameToRecommend($player, $currentGameSlug);

        if ($recommendation) {
            $this->recordPromoShown($player->id);
        }

        return $recommendation;
    }

    /**
     * Get contextual recommendation based on wait time
     */
    public function getWaitTimeRecommendation(Player $player, int $estimatedWaitMinutes): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        // Recommend games based on estimated time
        $games = Game::active()->get();
        $recommendations = [];

        foreach ($games as $game) {
            $avgTime = $this->getAverageSessionTime($game->id);

            // If wait time is enough for at least one session
            if ($avgTime && ($estimatedWaitMinutes * 60) >= $avgTime) {
                $sessionsCount = floor(($estimatedWaitMinutes * 60) / $avgTime);
                $recommendations[] = [
                    'game' => [
                        'id' => $game->id,
                        'slug' => $game->slug,
                        'name' => $game->name,
                        'description' => $game->description,
                        'icon_url' => $game->icon_url,
                    ],
                    'message' => "Perfect time for {$game->name}!",
                    'estimated_sessions' => $sessionsCount,
                    'avg_session_minutes' => round($avgTime / 60),
                ];
            }
        }

        if (empty($recommendations)) {
            return null;
        }

        // Return the best match (most sessions possible)
        usort($recommendations, fn($a, $b) => $b['estimated_sessions'] <=> $a['estimated_sessions']);

        return $recommendations[0];
    }

    /**
     * Bridge to WaitPulse - check current wait time at a location
     */
    public function getWaitPulseContext(float $latitude, float $longitude): ?array
    {
        if (!$this->waitPulseUrl || !$this->waitPulseKey) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->waitPulseKey}",
            ])->timeout(5)->get("{$this->waitPulseUrl}/v1/places/nearby", [
                'lat' => $latitude,
                'lng' => $longitude,
                'radius' => 100, // meters
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $places = $data['data']['places'] ?? [];

                // Find closest place with wait time
                foreach ($places as $place) {
                    if (isset($place['current_wait_minutes'])) {
                        return [
                            'place_name' => $place['name'],
                            'wait_minutes' => $place['current_wait_minutes'],
                            'confidence' => $place['confidence'] ?? null,
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('WaitPulse API error', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Report wait time to WaitPulse from game app
     */
    public function reportWaitTime(
        string $placeId,
        int $waitMinutes,
        ?string $userId = null
    ): bool {
        if (!$this->waitPulseUrl || !$this->waitPulseKey) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->waitPulseKey}",
            ])->timeout(10)->post("{$this->waitPulseUrl}/v1/reports", [
                'place_id' => $placeId,
                'wait_minutes' => $waitMinutes,
                'source' => 'game_platform',
                'external_user_id' => $userId,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('WaitPulse report error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Find the best game to recommend to a player
     */
    protected function findBestGameToRecommend(Player $player, ?string $currentGameSlug): ?array
    {
        $games = Game::active()->get();
        $playerProgress = PlayerProgress::where('player_id', $player->id)
            ->get()
            ->keyBy('game_id');

        $candidates = [];

        foreach ($games as $game) {
            // Skip current game
            if ($game->slug === $currentGameSlug) {
                continue;
            }

            $progress = $playerProgress->get($game->id);
            $gamesPlayed = $progress?->games_played ?? 0;

            // Prioritize games not tried yet
            $priority = $gamesPlayed === 0 ? 100 : (1 / ($gamesPlayed + 1));

            $candidates[] = [
                'game' => $game,
                'priority' => $priority,
                'games_played' => $gamesPlayed,
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort by priority (highest first)
        usort($candidates, fn($a, $b) => $b['priority'] <=> $a['priority']);

        $selected = $candidates[0];
        $game = $selected['game'];

        $message = $selected['games_played'] === 0
            ? "Try {$game->name}!"
            : "Come back to {$game->name}!";

        return [
            'game' => [
                'id' => $game->id,
                'slug' => $game->slug,
                'name' => $game->name,
                'description' => $game->description,
                'icon_url' => $game->icon_url,
                'store_url_ios' => $game->store_url_ios,
                'store_url_android' => $game->store_url_android,
            ],
            'message' => $message,
            'is_new' => $selected['games_played'] === 0,
        ];
    }

    /**
     * Check if we should show a promo based on frequency
     */
    protected function shouldShowPromo(int $playerId): bool
    {
        $key = "crosspromo:shown:{$playerId}";
        $count = Cache::get($key, 0);

        return ($count % $this->frequency) === 0;
    }

    /**
     * Record that a promo was shown
     */
    protected function recordPromoShown(int $playerId): void
    {
        $key = "crosspromo:shown:{$playerId}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addDay());
    }

    /**
     * Get average session time for a game
     */
    protected function getAverageSessionTime(int $gameId): ?float
    {
        $key = "game:avg_time:{$gameId}";

        return Cache::remember($key, 3600, function () use ($gameId) {
            return \App\Models\GameSession::where('game_id', $gameId)
                ->where('status', 'completed')
                ->whereNotNull('duration_seconds')
                ->avg('duration_seconds');
        });
    }
}
