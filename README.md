# Game Platform API

A unified backend API for mobile puzzle games built with Laravel 11. Supports multiple games with shared authentication, progress tracking, streaks, leaderboards, and cross-promotion.

## Features

- **Multi-Game Support**: Single backend for multiple puzzle games
- **Daily Challenges**: AI-generated daily puzzles using Claude API
- **Streak System**: Daily streak tracking with freeze tokens
- **Leaderboards**: Daily, weekly, monthly, and all-time rankings
- **Achievements**: Global and game-specific achievements
- **Cross-Promotion**: Promote other games in the portfolio
- **WaitPulse Integration**: "Kill time while you wait" feature
- **Ad Rewards**: Track rewarded video ads for hints/freezes

## Included Games

1. **Decode Daily** - Daily cryptogram puzzles (Wordle for code-breakers)
2. **Stack & Sort** - Relaxing sort puzzle game
3. **Number Crunch** - Math-based block puzzle

## Requirements

- PHP 8.2+
- MySQL 8.0+ (or SQLite for development)
- Redis (recommended for caching/sessions)
- Composer

## Installation

```bash
# Clone/extract the project
cd game-platform-api

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Create SQLite database (for local dev)
touch database/database.sqlite

# Run migrations
php artisan migrate

# Seed games and achievements
php artisan db:seed
```

## Configuration

### Environment Variables

```env
# Database (MySQL for production)
DB_CONNECTION=mysql
DB_HOST=your-rds-endpoint.amazonaws.com
DB_DATABASE=game_platform
DB_USERNAME=admin
DB_PASSWORD=your-password

# Redis (ElastiCache for production)
REDIS_HOST=your-elasticache-endpoint.cache.amazonaws.com

# Claude API (for puzzle generation)
CLAUDE_API_KEY=your-claude-api-key

# Platform Settings
PLATFORM_TIMEZONE=America/Denver
DAILY_RESET_HOUR=0
INITIAL_HINTS=3

# WaitPulse Integration (optional)
WAITPULSE_ENABLED=true
WAITPULSE_API_URL=https://your-waitpulse-api.com
WAITPULSE_API_KEY=your-waitpulse-key
```

## API Endpoints

### Authentication

```
POST   /v1/auth/register          Register new user
POST   /v1/auth/login             Email/password login
POST   /v1/auth/social/{provider} Apple/Google sign-in
POST   /v1/auth/logout            Logout (revoke token)
POST   /v1/auth/refresh           Refresh API token
GET    /v1/me                     Current user info
```

### Player

```
GET    /v1/player                 Player profile & stats
PATCH  /v1/player                 Update display name/avatar
PATCH  /v1/player/preferences     Update settings
GET    /v1/player/achievements    Unlocked achievements
GET    /v1/player/history         Game history
GET    /v1/player/streaks         Streak status per game
```

### Games

```
GET    /v1/games                  List all games
GET    /v1/games/{slug}           Game details
GET    /v1/games/{slug}/daily     Today's challenge
GET    /v1/games/{slug}/challenge/{n}  Specific challenge
GET    /v1/games/{slug}/leaderboard    Leaderboard
```

### Sessions

```
POST   /v1/sessions               Start game session
PATCH  /v1/sessions/{id}          Update progress
POST   /v1/sessions/{id}/complete Complete session
POST   /v1/sessions/{id}/abandon  Abandon session
POST   /v1/sessions/{id}/hint     Use a hint
```

### Rewards

```
POST   /v1/streak-freeze          Use streak freeze
POST   /v1/ad-watched             Record ad for reward
```

## Generating Daily Challenges

Challenges are generated using Claude AI:

```bash
# Generate 7 days ahead for all games
php artisan challenges:generate

# Generate for specific game
php artisan challenges:generate --game=decode-daily

# Generate more days
php artisan challenges:generate --days=14
```

Set up a cron job to run daily:

```bash
0 0 * * * cd /path/to/game-platform-api && php artisan challenges:generate >> /dev/null 2>&1
```

## AWS Deployment (Elastic Beanstalk)

### Architecture

```
CloudFront (CDN/SSL)
       ↓
Elastic Beanstalk (Laravel API)
       ↓
  ┌────┴────┐
  ↓         ↓
RDS MySQL  ElastiCache Redis
```

### Beanstalk Configuration

Create `.ebextensions/01-laravel.config`:

```yaml
option_settings:
  aws:elasticbeanstalk:container:php:phpini:
    document_root: /public
    memory_limit: 256M
  aws:elasticbeanstalk:application:environment:
    APP_ENV: production
    APP_DEBUG: false

container_commands:
  01_migrate:
    command: "php artisan migrate --force"
  02_cache:
    command: "php artisan config:cache && php artisan route:cache"
```

### Deploy

```bash
# Install EB CLI
pip install awsebcli

# Initialize
eb init game-platform-api --platform php --region us-west-2

# Create environment
eb create production --database --database.engine mysql

# Deploy
eb deploy
```

## Database Schema

### Core Tables

- `users` - Authentication accounts
- `players` - Gaming profiles (display name, hints, freezes)
- `games` - Game registry
- `daily_challenges` - Daily puzzle content
- `game_sessions` - Play sessions
- `player_progress` - Per-game stats & XP
- `streaks` - Daily streak tracking
- `achievements` - Achievement definitions
- `player_achievements` - Unlocked achievements
- `leaderboard_entries` - Scores by period

## Services

### ContentGenerator

Generates puzzles using Claude API:

```php
$generator = new ContentGenerator();

// Cryptogram
$puzzle = $generator->generateCryptogram(difficulty: 3, category: 'Science');

// Sort puzzle
$puzzle = $generator->generateSortPuzzle(difficulty: 2);

// Number puzzle
$puzzle = $generator->generateNumberPuzzle(difficulty: 4);
```

### ProgressTracker

Handles session completion and rewards:

```php
$tracker = new ProgressTracker();

// Start session
$session = $tracker->startSession($player, $game, 'daily', $challenge);

// Complete session
$result = $tracker->completeSession($session, score: 850);
// Returns: streak info, achievements, level up, hints earned
```

### CrossPromoService

Cross-promotion and WaitPulse integration:

```php
$promo = new CrossPromoService();

// Get game recommendation
$recommendation = $promo->getRecommendation($player, currentGame: 'decode-daily');

// Get recommendation based on wait time
$recommendation = $promo->getWaitTimeRecommendation($player, waitMinutes: 25);

// Check WaitPulse for nearby place
$context = $promo->getWaitPulseContext(lat: 39.7392, lng: -104.9903);
```

## Flutter Integration

Example API client usage:

```dart
final api = GamePlatformApi(baseUrl: 'https://api.yourdomain.com');

// Login
final auth = await api.login(email, password);
api.setToken(auth.token);

// Get today's challenge
final challenge = await api.getDaily('decode-daily');

// Start session
final session = await api.startSession(
  gameId: challenge.gameId,
  challengeId: challenge.id,
);

// Complete
final result = await api.completeSession(
  sessionId: session.id,
  score: 950,
  solution: userSolution,
);

// Show streak, achievements, etc.
print('Streak: ${result.streak.current}');
print('Achievements: ${result.achievements}');
```

## Testing

```bash
# Run tests
php artisan test

# With coverage
php artisan test --coverage
```

## License

Proprietary - Diamond Games

---

Built with ❤️ for puzzle lovers
