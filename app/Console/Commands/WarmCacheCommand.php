<?php

namespace App\Console\Commands;

use App\Models\Region;
use App\Services\Insights\DataIntake;
use App\Services\Insights\EngineeringShowcase;
use App\Services\Insights\RegionSummary;
use Illuminate\Console\Command;

class WarmCacheCommand extends Command
{
    protected $signature = 'continuum:warm-cache';

    protected $description = 'Precompute the dashboard summaries (global totals, per-area cards, data intake) so no visitor waits on them';

    public function handle(RegionSummary $summary, DataIntake $intake, EngineeringShowcase $showcase): int
    {
        $this->components->task('Global summary', fn () => $summary->refreshGlobal());
        $this->components->task('Area summaries', fn () => Region::query()->each(fn (Region $region) => $summary->for($region)));
        $this->components->task('Data intake', fn () => $intake->refresh());
        $this->components->task('How it works', fn () => $showcase->refresh());

        return self::SUCCESS;
    }
}
