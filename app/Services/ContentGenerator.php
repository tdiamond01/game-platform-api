<?php

namespace App\Services;

use App\Models\DailyChallenge;
use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ContentGenerator
{
    protected string $apiKey;
    protected string $model;
    protected int $maxTokens;
    protected float $temperature;

    public function __construct()
    {
        $this->apiKey = config('gameplatform.content.claude_api_key');
        $this->model = config('gameplatform.content.claude_model', 'claude-sonnet-4-20250514');
        $this->maxTokens = config('gameplatform.content.max_tokens', 2048);
        $this->temperature = config('gameplatform.content.temperature', 0.8);
    }

    /**
     * Generate a cryptogram puzzle (for Decode Daily)
     */
    public function generateCryptogram(int $difficulty = 2, ?string $category = null): array
    {
        $categoryPrompt = $category ? "from the category: {$category}" : "from any inspiring category";
        
        $prompt = <<<PROMPT
Generate a cryptogram puzzle for a mobile word game. Difficulty level: {$difficulty}/5.

Requirements:
1. Select a famous quote {$categoryPrompt}
2. The quote should be {$this->getDifficultyLength($difficulty)} characters
3. Create a letter substitution cipher (each letter maps to exactly one other letter)
4. Provide 3 progressive hints

Respond with ONLY valid JSON in this exact format:
{
    "quote": "THE ORIGINAL QUOTE IN UPPERCASE",
    "author": "Author Name",
    "category": "category name",
    "cipher": {"A": "X", "B": "Y", ...},
    "encoded": "THE ENCODED VERSION",
    "hints": [
        {"type": "letter", "original": "E", "encoded": "X", "description": "Most common letter"},
        {"type": "word", "word": "THE", "description": "Common 3-letter word"},
        {"type": "pattern", "description": "Look for double letters"}
    ]
}
PROMPT;

        $response = $this->callClaude($prompt);
        
        if (!$response) {
            return $this->getFallbackCryptogram();
        }

        return $this->parseCryptogramResponse($response);
    }

    /**
     * Generate a sort puzzle (for Stack & Sort)
     */
    public function generateSortPuzzle(int $difficulty = 2): array
    {
        $containerCount = $this->getSortContainerCount($difficulty);
        $itemsPerContainer = $this->getSortItemsPerContainer($difficulty);
        $extraContainers = $difficulty > 3 ? 1 : 2;

        $prompt = <<<PROMPT
Generate a sort puzzle for a mobile game. Think of games like Ball Sort or Water Sort.

Parameters:
- {$containerCount} colors/item types
- {$itemsPerContainer} items per color
- {$extraContainers} empty containers for sorting

Create a shuffled starting state that is solvable but requires thought.

Respond with ONLY valid JSON:
{
    "colors": ["red", "blue", "green", ...],
    "containers": [
        ["red", "blue", "green", "red"],
        ["blue", "green", "red", "blue"],
        ...
        []
    ],
    "solution_moves": 15,
    "difficulty_rating": {$difficulty}
}
PROMPT;

        $response = $this->callClaude($prompt);
        
        if (!$response) {
            return $this->getFallbackSortPuzzle($difficulty);
        }

        return $this->parseSortPuzzleResponse($response);
    }

    /**
     * Generate a number block puzzle (for Number Crunch)
     */
    public function generateNumberPuzzle(int $difficulty = 2): array
    {
        $gridSize = $this->getNumberGridSize($difficulty);
        $targetSum = $this->getNumberTargetSum($difficulty);

        $prompt = <<<PROMPT
Generate a number block puzzle. Players place numbered blocks to make rows/columns sum to a target.

Parameters:
- Grid size: {$gridSize}x{$gridSize}
- Target sum for each row/column: {$targetSum}
- Difficulty: {$difficulty}/5

Create an interesting puzzle with multiple valid solutions but requiring strategy.

Respond with ONLY valid JSON:
{
    "grid_size": {$gridSize},
    "target_sum": {$targetSum},
    "initial_blocks": [
        {"value": 5, "row": 0, "col": 0, "fixed": true},
        {"value": 3, "row": 1, "col": 2, "fixed": true}
    ],
    "available_blocks": [1, 2, 3, 4, 5, 6, 7, 8, 9],
    "solution": [[5, 2, 3], [1, 6, 3], [4, 2, 4]],
    "par_moves": 12
}
PROMPT;

        $response = $this->callClaude($prompt);
        
        if (!$response) {
            return $this->getFallbackNumberPuzzle($difficulty);
        }

        return $this->parseNumberPuzzleResponse($response);
    }

    /**
     * Generate challenges for upcoming days
     */
    public function generateDailyChallenges(Game $game, int $days = 7): array
    {
        $generated = [];
        $timezone = config('gameplatform.daily.timezone', 'America/Denver');
        $startDate = now($timezone)->addDay();

        // Get last challenge number
        $lastChallenge = $game->challenges()->orderByDesc('challenge_number')->first();
        $nextNumber = $lastChallenge ? $lastChallenge->challenge_number + 1 : 1;

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateString = $date->format('Y-m-d');

            // Skip if already exists
            if ($game->challenges()->where('challenge_date', $dateString)->exists()) {
                continue;
            }

            // Vary difficulty by day of week (harder on weekends)
            $dayOfWeek = $date->dayOfWeek;
            $difficulty = match ($dayOfWeek) {
                0, 6 => 4,  // Weekend: hard
                1 => 1,      // Monday: easy
                5 => 3,      // Friday: medium-hard
                default => 2 // Tue-Thu: medium
            };

            $content = $this->generateForGameType($game->type, $difficulty);

            if ($content) {
                $challenge = DailyChallenge::create([
                    'game_id' => $game->id,
                    'challenge_date' => $dateString,
                    'challenge_number' => $nextNumber++,
                    'difficulty' => $difficulty,
                    'content' => $content['content'],
                    'solution' => $content['solution'],
                    'hints' => $content['hints'] ?? [],
                    'metadata' => $content['metadata'] ?? [],
                    'is_active' => true,
                    'generated_by' => 'claude',
                ]);
                $generated[] = $challenge;
            }
        }

        return $generated;
    }

    /**
     * Generate content based on game type
     */
    protected function generateForGameType(string $type, int $difficulty): ?array
    {
        return match ($type) {
            'cryptogram' => $this->formatCryptogramForChallenge($this->generateCryptogram($difficulty)),
            'sort_puzzle' => $this->formatSortForChallenge($this->generateSortPuzzle($difficulty)),
            'math_block' => $this->formatNumberForChallenge($this->generateNumberPuzzle($difficulty)),
            default => null,
        };
    }

    /**
     * Call Claude API
     */
    protected function callClaude(string $prompt): ?string
    {
        if (!$this->apiKey) {
            Log::warning('Claude API key not configured');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['content'][0]['text'] ?? null;
            }

            Log::error('Claude API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('Claude API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    protected function getDifficultyLength(int $difficulty): string
    {
        return match ($difficulty) {
            1 => '30-50',
            2 => '50-80',
            3 => '80-120',
            4 => '120-160',
            5 => '160-200',
            default => '50-80',
        };
    }

    protected function getSortContainerCount(int $difficulty): int
    {
        return match ($difficulty) {
            1 => 3,
            2 => 4,
            3 => 5,
            4 => 6,
            5 => 7,
            default => 4,
        };
    }

    protected function getSortItemsPerContainer(int $difficulty): int
    {
        return $difficulty >= 4 ? 5 : 4;
    }

    protected function getNumberGridSize(int $difficulty): int
    {
        return match ($difficulty) {
            1 => 3,
            2, 3 => 4,
            4, 5 => 5,
            default => 4,
        };
    }

    protected function getNumberTargetSum(int $difficulty): int
    {
        return match ($difficulty) {
            1 => 10,
            2 => 15,
            3 => 20,
            4 => 25,
            5 => 30,
            default => 15,
        };
    }

    protected function parseCryptogramResponse(string $response): array
    {
        try {
            // Extract JSON from response
            $json = $this->extractJson($response);
            return json_decode($json, true) ?? $this->getFallbackCryptogram();
        } catch (\Exception $e) {
            return $this->getFallbackCryptogram();
        }
    }

    protected function parseSortPuzzleResponse(string $response): array
    {
        try {
            $json = $this->extractJson($response);
            return json_decode($json, true) ?? $this->getFallbackSortPuzzle(2);
        } catch (\Exception $e) {
            return $this->getFallbackSortPuzzle(2);
        }
    }

    protected function parseNumberPuzzleResponse(string $response): array
    {
        try {
            $json = $this->extractJson($response);
            return json_decode($json, true) ?? $this->getFallbackNumberPuzzle(2);
        } catch (\Exception $e) {
            return $this->getFallbackNumberPuzzle(2);
        }
    }

    protected function extractJson(string $text): string
    {
        // Try to find JSON in the response
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            return $matches[0];
        }
        return $text;
    }

    protected function formatCryptogramForChallenge(array $data): array
    {
        return [
            'content' => [
                'encoded' => $data['encoded'] ?? '',
                'author' => $data['author'] ?? 'Unknown',
                'category' => $data['category'] ?? 'General',
                'letter_count' => strlen(preg_replace('/[^A-Z]/', '', $data['quote'] ?? '')),
            ],
            'solution' => [
                'quote' => $data['quote'] ?? '',
                'cipher' => $data['cipher'] ?? [],
            ],
            'hints' => $data['hints'] ?? [],
            'metadata' => [
                'word_count' => str_word_count($data['quote'] ?? ''),
            ],
        ];
    }

    protected function formatSortForChallenge(array $data): array
    {
        return [
            'content' => [
                'colors' => $data['colors'] ?? [],
                'containers' => $data['containers'] ?? [],
            ],
            'solution' => [
                'moves' => $data['solution_moves'] ?? 0,
            ],
            'hints' => [],
            'metadata' => [
                'difficulty_rating' => $data['difficulty_rating'] ?? 2,
            ],
        ];
    }

    protected function formatNumberForChallenge(array $data): array
    {
        return [
            'content' => [
                'grid_size' => $data['grid_size'] ?? 4,
                'target_sum' => $data['target_sum'] ?? 15,
                'initial_blocks' => $data['initial_blocks'] ?? [],
                'available_blocks' => $data['available_blocks'] ?? [],
            ],
            'solution' => $data['solution'] ?? [],
            'hints' => [],
            'metadata' => [
                'par_moves' => $data['par_moves'] ?? 10,
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Fallback Content
    |--------------------------------------------------------------------------
    */

    protected function getFallbackCryptogram(): array
    {
        // Pre-generated fallback
        return [
            'quote' => 'THE ONLY WAY TO DO GREAT WORK IS TO LOVE WHAT YOU DO',
            'author' => 'Steve Jobs',
            'category' => 'Inspiration',
            'cipher' => ['A'=>'Q','B'=>'W','C'=>'E','D'=>'R','E'=>'T','F'=>'Y','G'=>'U','H'=>'I','I'=>'O','J'=>'P','K'=>'A','L'=>'S','M'=>'D','N'=>'F','O'=>'G','P'=>'H','Q'=>'J','R'=>'K','S'=>'L','T'=>'Z','U'=>'X','V'=>'C','W'=>'V','X'=>'B','Y'=>'N','Z'=>'M'],
            'encoded' => 'ZIT GFSN VQN ZG RG UKTQZ VGKA OL ZG SGCT VIQZ NGX RG',
            'hints' => [
                ['type' => 'letter', 'original' => 'T', 'encoded' => 'Z', 'description' => 'Common starting letter'],
                ['type' => 'word', 'word' => 'THE', 'description' => 'Three letter word at start'],
                ['type' => 'letter', 'original' => 'O', 'encoded' => 'G', 'description' => 'Common vowel'],
            ],
        ];
    }

    protected function getFallbackSortPuzzle(int $difficulty): array
    {
        return [
            'colors' => ['red', 'blue', 'green', 'yellow'],
            'containers' => [
                ['red', 'blue', 'green', 'yellow'],
                ['blue', 'green', 'yellow', 'red'],
                ['green', 'yellow', 'red', 'blue'],
                ['yellow', 'red', 'blue', 'green'],
                [],
                [],
            ],
            'solution_moves' => 20,
            'difficulty_rating' => $difficulty,
        ];
    }

    protected function getFallbackNumberPuzzle(int $difficulty): array
    {
        return [
            'grid_size' => 4,
            'target_sum' => 15,
            'initial_blocks' => [
                ['value' => 5, 'row' => 0, 'col' => 0, 'fixed' => true],
                ['value' => 8, 'row' => 2, 'col' => 2, 'fixed' => true],
            ],
            'available_blocks' => [1, 2, 3, 4, 5, 6, 7, 8, 9],
            'solution' => [],
            'par_moves' => 14,
        ];
    }
}
