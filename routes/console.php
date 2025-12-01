<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily challenge generation
Schedule::command('challenges:generate --days=7')
    ->dailyAt('00:00')
    ->timezone(config('gameplatform.daily.timezone', 'America/Denver'));
