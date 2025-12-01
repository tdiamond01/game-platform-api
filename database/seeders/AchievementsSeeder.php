<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Game;
use Illuminate\Database\Seeder;

class AchievementsSeeder extends Seeder
{
    public function run(): void
    {
        // Global achievements (apply to all games)
        $globalAchievements = [
            // Streak achievements
            [
                'slug' => 'streak-7',
                'name' => 'Week Warrior',
                'description' => 'Complete 7 days in a row',
                'icon' => '🔥',
                'category' => 'streak',
                'points' => 50,
                'requirement_type' => 'streak',
                'requirement_value' => 7,
            ],
            [
                'slug' => 'streak-30',
                'name' => 'Monthly Master',
                'description' => 'Complete 30 days in a row',
                'icon' => '🌟',
                'category' => 'streak',
                'points' => 200,
                'requirement_type' => 'streak',
                'requirement_value' => 30,
            ],
            [
                'slug' => 'streak-100',
                'name' => 'Century Club',
                'description' => 'Complete 100 days in a row',
                'icon' => '💯',
                'category' => 'streak',
                'points' => 500,
                'requirement_type' => 'streak',
                'requirement_value' => 100,
            ],
            [
                'slug' => 'streak-365',
                'name' => 'Year of Dedication',
                'description' => 'Complete 365 days in a row',
                'icon' => '🏆',
                'category' => 'streak',
                'points' => 1000,
                'requirement_type' => 'streak',
                'requirement_value' => 365,
                'is_hidden' => true,
            ],

            // Progress achievements
            [
                'slug' => 'first-win',
                'name' => 'First Victory',
                'description' => 'Complete your first puzzle',
                'icon' => '🎯',
                'category' => 'progress',
                'points' => 10,
                'requirement_type' => 'games_won',
                'requirement_value' => 1,
            ],
            [
                'slug' => 'games-10',
                'name' => 'Getting Started',
                'description' => 'Complete 10 puzzles',
                'icon' => '📈',
                'category' => 'progress',
                'points' => 25,
                'requirement_type' => 'games_won',
                'requirement_value' => 10,
            ],
            [
                'slug' => 'games-50',
                'name' => 'Dedicated Player',
                'description' => 'Complete 50 puzzles',
                'icon' => '⭐',
                'category' => 'progress',
                'points' => 75,
                'requirement_type' => 'games_won',
                'requirement_value' => 50,
            ],
            [
                'slug' => 'games-100',
                'name' => 'Puzzle Enthusiast',
                'description' => 'Complete 100 puzzles',
                'icon' => '🌠',
                'category' => 'progress',
                'points' => 150,
                'requirement_type' => 'games_won',
                'requirement_value' => 100,
            ],
            [
                'slug' => 'games-500',
                'name' => 'Puzzle Master',
                'description' => 'Complete 500 puzzles',
                'icon' => '👑',
                'category' => 'progress',
                'points' => 300,
                'requirement_type' => 'games_won',
                'requirement_value' => 500,
            ],

            // Daily challenge achievements
            [
                'slug' => 'daily-10',
                'name' => 'Daily Dabbler',
                'description' => 'Complete 10 daily challenges',
                'icon' => '📅',
                'category' => 'progress',
                'points' => 30,
                'requirement_type' => 'daily_completed',
                'requirement_value' => 10,
            ],
            [
                'slug' => 'daily-50',
                'name' => 'Daily Devotee',
                'description' => 'Complete 50 daily challenges',
                'icon' => '📆',
                'category' => 'progress',
                'points' => 100,
                'requirement_type' => 'daily_completed',
                'requirement_value' => 50,
            ],

            // Level achievements
            [
                'slug' => 'level-5',
                'name' => 'Rising Star',
                'description' => 'Reach level 5',
                'icon' => '🌱',
                'category' => 'progress',
                'points' => 25,
                'requirement_type' => 'level',
                'requirement_value' => 5,
            ],
            [
                'slug' => 'level-10',
                'name' => 'Experienced',
                'description' => 'Reach level 10',
                'icon' => '🌿',
                'category' => 'progress',
                'points' => 50,
                'requirement_type' => 'level',
                'requirement_value' => 10,
            ],
            [
                'slug' => 'level-25',
                'name' => 'Veteran',
                'description' => 'Reach level 25',
                'icon' => '🌳',
                'category' => 'progress',
                'points' => 100,
                'requirement_type' => 'level',
                'requirement_value' => 25,
            ],

            // Special achievements
            [
                'slug' => 'perfect-game',
                'name' => 'Flawless',
                'description' => 'Complete a puzzle with no mistakes and no hints',
                'icon' => '💎',
                'category' => 'mastery',
                'points' => 50,
                'requirement_type' => 'perfect_game',
                'requirement_value' => 1,
            ],
            [
                'slug' => 'no-hints',
                'name' => 'Independent',
                'description' => 'Complete a puzzle without using hints',
                'icon' => '🧠',
                'category' => 'mastery',
                'points' => 25,
                'requirement_type' => 'no_hints',
                'requirement_value' => 1,
            ],
            [
                'slug' => 'speed-60',
                'name' => 'Speed Demon',
                'description' => 'Complete a puzzle in under 60 seconds',
                'icon' => '⚡',
                'category' => 'speed',
                'points' => 40,
                'requirement_type' => 'speed',
                'requirement_value' => 60,
            ],
            [
                'slug' => 'speed-30',
                'name' => 'Lightning Fast',
                'description' => 'Complete a puzzle in under 30 seconds',
                'icon' => '🚀',
                'category' => 'speed',
                'points' => 75,
                'requirement_type' => 'speed',
                'requirement_value' => 30,
            ],
        ];

        // Create global achievements
        $sortOrder = 0;
        foreach ($globalAchievements as $achievement) {
            Achievement::updateOrCreate(
                ['slug' => $achievement['slug'], 'game_id' => null],
                array_merge($achievement, [
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ])
            );
        }

        $this->command->info('Seeded ' . count($globalAchievements) . ' global achievements.');

        // Game-specific achievements
        $this->seedDecodeAchievements();
        $this->seedStackSortAchievements();
        $this->seedNumberCrunchAchievements();
    }

    protected function seedDecodeAchievements(): void
    {
        $game = Game::where('slug', 'decode-daily')->first();
        if (!$game) return;

        $achievements = [
            [
                'slug' => 'decode-first-letter',
                'name' => 'Codebreaker',
                'description' => 'Solve your first cryptogram',
                'icon' => '🔓',
                'category' => 'progress',
                'points' => 15,
                'requirement_type' => 'games_won',
                'requirement_value' => 1,
            ],
            [
                'slug' => 'decode-no-vowels',
                'name' => 'Consonant King',
                'description' => 'Solve without revealing any vowels',
                'icon' => '👀',
                'category' => 'mastery',
                'points' => 60,
                'requirement_type' => 'custom',
                'requirement_value' => 1,
                'is_hidden' => true,
            ],
            [
                'slug' => 'decode-all-categories',
                'name' => 'Well Rounded',
                'description' => 'Complete a puzzle from each category',
                'icon' => '🎨',
                'category' => 'special',
                'points' => 100,
                'requirement_type' => 'custom',
                'requirement_value' => 6,
            ],
        ];

        $sortOrder = 100;
        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(
                ['slug' => $achievement['slug'], 'game_id' => $game->id],
                array_merge($achievement, [
                    'game_id' => $game->id,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ])
            );
        }
    }

    protected function seedStackSortAchievements(): void
    {
        $game = Game::where('slug', 'stack-sort')->first();
        if (!$game) return;

        $achievements = [
            [
                'slug' => 'stack-zen-master',
                'name' => 'Zen Master',
                'description' => 'Complete 10 puzzles in Zen mode',
                'icon' => '🧘',
                'category' => 'special',
                'points' => 50,
                'requirement_type' => 'custom',
                'requirement_value' => 10,
            ],
            [
                'slug' => 'stack-no-undo',
                'name' => 'No Regrets',
                'description' => 'Complete a puzzle without using undo',
                'icon' => '✨',
                'category' => 'mastery',
                'points' => 35,
                'requirement_type' => 'custom',
                'requirement_value' => 1,
            ],
            [
                'slug' => 'stack-minimal-moves',
                'name' => 'Efficiency Expert',
                'description' => 'Complete a puzzle in minimum possible moves',
                'icon' => '🎯',
                'category' => 'mastery',
                'points' => 75,
                'requirement_type' => 'custom',
                'requirement_value' => 1,
                'is_hidden' => true,
            ],
        ];

        $sortOrder = 200;
        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(
                ['slug' => $achievement['slug'], 'game_id' => $game->id],
                array_merge($achievement, [
                    'game_id' => $game->id,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ])
            );
        }
    }

    protected function seedNumberCrunchAchievements(): void
    {
        $game = Game::where('slug', 'number-crunch')->first();
        if (!$game) return;

        $achievements = [
            [
                'slug' => 'number-calculator',
                'name' => 'Human Calculator',
                'description' => 'Complete a hard puzzle without hints',
                'icon' => '🔢',
                'category' => 'mastery',
                'points' => 60,
                'requirement_type' => 'custom',
                'requirement_value' => 1,
            ],
            [
                'slug' => 'number-under-par',
                'name' => 'Under Par',
                'description' => 'Complete a puzzle in fewer moves than par',
                'icon' => '⛳',
                'category' => 'mastery',
                'points' => 50,
                'requirement_type' => 'custom',
                'requirement_value' => 1,
            ],
        ];

        $sortOrder = 300;
        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(
                ['slug' => $achievement['slug'], 'game_id' => $game->id],
                array_merge($achievement, [
                    'game_id' => $game->id,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ])
            );
        }
    }
}
