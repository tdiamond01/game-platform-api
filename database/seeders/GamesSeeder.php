<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GamesSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'slug' => 'decode-daily',
                'name' => 'Decode Daily',
                'type' => 'cryptogram',
                'description' => 'Crack the code and reveal a famous quote every day. Like Wordle for code-breakers!',
                'daily_enabled' => true,
                'has_leaderboard' => true,
                'is_active' => true,
                'settings' => [
                    'max_hints' => 3,
                    'time_bonus_threshold' => 120, // seconds
                    'perfect_game_bonus' => 500,
                    'categories' => ['Inspiration', 'History', 'Science', 'Literature', 'Sports', 'Pop Culture'],
                ],
                'launched_at' => now(),
            ],
            [
                'slug' => 'stack-sort',
                'name' => 'Stack & Sort',
                'type' => 'sort_puzzle',
                'description' => 'Sort colored items into matching containers. Relaxing, satisfying, and addictive!',
                'daily_enabled' => true,
                'has_leaderboard' => true,
                'is_active' => true,
                'settings' => [
                    'max_undo' => 3,
                    'zen_mode' => true,
                    'sound_effects' => ['pop', 'whoosh', 'complete'],
                ],
                'launched_at' => now(),
            ],
            [
                'slug' => 'number-crunch',
                'name' => 'Number Crunch',
                'type' => 'math_block',
                'description' => 'Block puzzle meets math! Place numbered blocks to match target sums.',
                'daily_enabled' => true,
                'has_leaderboard' => true,
                'is_active' => true,
                'settings' => [
                    'show_sum_preview' => true,
                    'highlight_valid_moves' => true,
                ],
                'launched_at' => now(),
            ],
        ];

        foreach ($games as $gameData) {
            Game::updateOrCreate(
                ['slug' => $gameData['slug']],
                $gameData
            );
        }

        $this->command->info('Seeded ' . count($games) . ' games.');
    }
}
