<?php

use App\Services\Firms\BackfillPlanner;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Schedule;

// `composer run dev` should drain the priority lane (new areas' newest windows,
// calendar refreshes) before the default backfill queue.
DevCommands::bufferSize(300);
DevCommands::except('queue');
DevCommands::artisan('queue:listen --queue='.BackfillPlanner::PRIORITY_QUEUE.',default --tries=1 --timeout=0', 'worker');

Schedule::command('firms:ingest-public --window=48h --presets')
    ->dailyAt('03:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('continuum:harmonize')
    ->dailyAt('04:30')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();
