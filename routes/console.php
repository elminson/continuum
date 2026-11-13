<?php

use App\Services\Firms\BackfillPlanner;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Schedule;

// `composer run dev` should drain the priority lane (new areas' newest windows,
// calendar refreshes) before the default backfill queue.
DevCommands::bufferSize(300);
DevCommands::except('queue');
DevCommands::artisan('queue:listen --queue='.BackfillPlanner::PRIORITY_QUEUE.',default --tries=1 --timeout=0 --memory=512', 'worker');

Schedule::command('firms:ingest-public --presets --window='.config('continuum.schedule.ingest_public_window', '24h'))
    ->cron((string) config('continuum.schedule.ingest_public_cron', '5 * * * *'))
    ->timezone('UTC')
    ->runInBackground()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('continuum:harmonize', [
    '--from' => now()->subDays((int) config('continuum.schedule.harmonize_lookback_days', 3))->toDateString(),
])
    ->cron((string) config('continuum.schedule.harmonize_cron', '25 * * * *'))
    ->timezone('UTC')
    ->runInBackground()
    ->withoutOverlapping()
    ->onOneServer();

// Dashboard aggregates scan millions of cell-days; keep them precomputed.
Schedule::command('continuum:warm-cache')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Advisory readings of new or changed unusual periods by Laya, after each
// hourly harmonization. Skips quietly when the model is not reachable.
Schedule::command('continuum:triage-alerts')
    ->cron((string) config('continuum.schedule.triage_cron', '50 * * * *'))
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
