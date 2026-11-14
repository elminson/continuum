<?php

namespace App\Console\Commands;

use App\Enums\Locale;
use App\Models\Region;
use App\Services\Insights\DataIntake;
use App\Services\Insights\EngineeringShowcase;
use App\Services\Insights\ModisRetirement;
use App\Services\Insights\RegionSummary;
use Illuminate\Console\Command;

class WarmCacheCommand extends Command
{
    protected $signature = 'continuum:warm-cache';

    protected $description = 'Precompute the dashboard summaries (global totals, per-area cards, data intake) so no visitor waits on them';

    public function handle(RegionSummary $summary, DataIntake $intake, EngineeringShowcase $showcase, ModisRetirement $retirement): int
    {
        $this->components->task('Global summary', fn () => $summary->refreshGlobal());

        // Area cards, intake and showcase carry translated text: warm each language.
        $original = app()->getLocale();

        foreach (Locale::cases() as $locale) {
            app()->setLocale($locale->value);

            $this->components->task("Area summaries ({$locale->value})", fn () => Region::query()->each(fn (Region $region) => $summary->for($region)));
            $this->components->task("Data intake ({$locale->value})", fn () => $intake->refresh());
            $this->components->task("How it works ({$locale->value})", fn () => $showcase->refresh());
        }

        app()->setLocale($original);

        // Cached per harmonization run, so this only recomputes after new data.
        $this->components->task('MODIS retirement', fn () => Region::query()->where('is_preset', true)->each(fn (Region $region) => $retirement->simulate($region)));

        return self::SUCCESS;
    }
}
