<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\ContentGenerator;
use Illuminate\Console\Command;

class GenerateDailyChallenges extends Command
{
    protected $signature = 'challenges:generate 
                            {--game= : Specific game slug to generate for}
                            {--days=7 : Number of days ahead to generate}
                            {--force : Regenerate existing challenges}';

    protected $description = 'Generate daily challenges for games using Claude AI';

    public function handle(ContentGenerator $generator): int
    {
        $gameSlug = $this->option('game');
        $days = (int) $this->option('days');
        $force = $this->option('force');

        if (!config('gameplatform.content.claude_api_key')) {
            $this->error('Claude API key not configured. Set CLAUDE_API_KEY in .env');
            return Command::FAILURE;
        }

        $query = Game::active()->where('daily_enabled', true);

        if ($gameSlug) {
            $query->where('slug', $gameSlug);
        }

        $games = $query->get();

        if ($games->isEmpty()) {
            $this->warn('No games found with daily challenges enabled.');
            return Command::SUCCESS;
        }

        $totalGenerated = 0;

        foreach ($games as $game) {
            $this->info("Generating challenges for {$game->name}...");

            try {
                $challenges = $generator->generateDailyChallenges($game, $days);
                $count = count($challenges);
                $totalGenerated += $count;

                if ($count > 0) {
                    $this->info("  ✓ Generated {$count} challenges");
                    foreach ($challenges as $challenge) {
                        $this->line("    - #{$challenge->challenge_number} ({$challenge->challenge_date->format('Y-m-d')})");
                    }
                } else {
                    $this->line("  → All upcoming challenges already exist");
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Done! Generated {$totalGenerated} total challenges.");

        return Command::SUCCESS;
    }
}
