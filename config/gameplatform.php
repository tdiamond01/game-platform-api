<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Game Platform Configuration
    |--------------------------------------------------------------------------
    */

    'name' => env('PLATFORM_NAME', 'Diamond Games'),
    'version' => env('PLATFORM_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Daily Challenge Settings
    |--------------------------------------------------------------------------
    */
    'daily' => [
        'timezone' => env('PLATFORM_TIMEZONE', 'America/Denver'),
        'reset_hour' => env('DAILY_RESET_HOUR', 0),
        'generate_ahead_days' => env('DAILY_GENERATE_AHEAD', 7),
        'cache_ttl' => env('DAILY_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Streak Settings
    |--------------------------------------------------------------------------
    */
    'streaks' => [
        'grace_period_hours' => env('STREAK_GRACE_HOURS', 12),
        'initial_freezes' => env('STREAK_INITIAL_FREEZES', 1),
        'max_freezes' => env('STREAK_MAX_FREEZES', 5),
        'milestones' => [7, 30, 100, 365],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rewards & Economy
    |--------------------------------------------------------------------------
    */
    'rewards' => [
        'initial_hints' => env('INITIAL_HINTS', 3),
        'hints_per_completion' => env('HINTS_PER_COMPLETION', 0),
        'hints_per_milestone' => env('HINTS_PER_MILESTONE', 2),
        'hint_costs' => [
            'reveal_letter' => 1,
            'reveal_word' => 2,
            'skip_puzzle' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Leaderboards
    |--------------------------------------------------------------------------
    */
    'leaderboards' => [
        'display_limit' => env('LEADERBOARD_LIMIT', 100),
        'cache_ttl' => env('LEADERBOARD_CACHE_TTL', 300),
        'periods' => ['daily', 'weekly', 'monthly', 'alltime'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Generation (Claude API)
    |--------------------------------------------------------------------------
    */
    'content' => [
        'claude_api_key' => env('CLAUDE_API_KEY'),
        'claude_model' => env('CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => env('CLAUDE_MAX_TOKENS', 2048),
        'temperature' => env('CLAUDE_TEMPERATURE', 0.8),
        'rate_limit' => env('CLAUDE_RATE_LIMIT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cross-Promotion
    |--------------------------------------------------------------------------
    */
    'crosspromo' => [
        'enabled' => env('CROSSPROMO_ENABLED', true),
        'frequency' => env('CROSSPROMO_FREQUENCY', 5),
        'waitpulse' => [
            'enabled' => env('WAITPULSE_ENABLED', false),
            'api_url' => env('WAITPULSE_API_URL'),
            'api_key' => env('WAITPULSE_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'enabled' => env('ANALYTICS_ENABLED', true),
        'events' => [
            'session_start',
            'session_complete',
            'hint_used',
            'ad_watched',
            'streak_milestone',
            'achievement_unlocked',
            'share_result',
        ],
        'retention_days' => [1, 7, 14, 30, 60, 90],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'api_per_minute' => env('RATE_LIMIT_API', 60),
        'sessions_per_hour' => env('RATE_LIMIT_SESSIONS', 30),
        'hints_per_hour' => env('RATE_LIMIT_HINTS', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Games
    |--------------------------------------------------------------------------
    */
    'games' => [
        'decode_daily' => [
            'name' => 'Decode Daily',
            'slug' => 'decode-daily',
            'type' => 'cryptogram',
            'description' => 'Decode a famous quote every day',
            'daily_enabled' => true,
            'has_leaderboard' => true,
        ],
        'stack_sort' => [
            'name' => 'Stack & Sort',
            'slug' => 'stack-sort',
            'type' => 'sort_puzzle',
            'description' => 'Sort items into matching containers',
            'daily_enabled' => true,
            'has_leaderboard' => true,
        ],
        'number_crunch' => [
            'name' => 'Number Crunch',
            'slug' => 'number-crunch',
            'type' => 'math_block',
            'description' => 'Clear blocks by matching sums',
            'daily_enabled' => true,
            'has_leaderboard' => true,
        ],
    ],

];
