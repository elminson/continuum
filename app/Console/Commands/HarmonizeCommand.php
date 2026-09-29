<?php

namespace App\Console\Commands;

use App\Actions\Harmonization\AggregateCellDays;
use App\Actions\Harmonization\HarmonizeRegion;
use App\Models\Region;
use Illuminate\Console\Command;

class HarmonizeCommand extends Command
{
    protected $signature = 'continuum:harmonize
        {region?* : Region slugs (default: all regions)}
        {--skip-aggregate : Do not rebuild cell-day aggregates first}
        {--from= : Only re-aggregate detections from this date}
        {--to= : Only re-aggregate detections up to this date}';

    protected $description = 'Aggregate detections to grid cell-days, rebuild calendars, calibrate MODIS↔VIIRS transfer and screen for anomalies';

    public function handle(AggregateCellDays $aggregate, HarmonizeRegion $harmonize): int
    {
        $regions = $this->argument('region') === []
            ? Region::query()->orderBy('id')->get()
            : Region::query()->whereIn('slug', (array) $this->argument('region'))->get();

        if ($regions->isEmpty()) {
            $this->error('No regions found.');

            return self::INVALID;
        }

        if (! $this->option('skip-aggregate')) {
            $this->components->task('Aggregating detections into grid cell-days', function () use ($aggregate): void {
                $written = $aggregate($this->option('from'), $this->option('to'), null, fn (string $from, string $to, int $rows) => $this->output->write("\r  {$from} → {$to}: {$rows} cell-days"));
                $this->newLine();
                $this->components->twoColumnDetail('  cell-day rows written', number_format($written));
            });
        }

        foreach ($regions as $region) {
            $this->components->info($region->name);
            $result = $harmonize($region);

            foreach ($result['entries'] as $period => $count) {
                $this->components->twoColumnDetail("  calendar entries ({$period})", number_format($count));
            }

            foreach ($result['calibration'] as $calibration) {
                if (($calibration['status'] ?? null) !== 'ok') {
                    $this->components->twoColumnDetail('  calibration', "skipped: {$calibration['status']} ({$calibration['overlap_days']} overlap days, {$calibration['active_days']} with activity, {$calibration['required_days']} needed)");

                    continue;
                }

                $validation = $calibration['validation'];
                $this->components->twoColumnDetail(
                    "  {$calibration['method']} → {$calibration['reference']}",
                    sprintf(
                        'factor %s [%s, %s] · %d overlap days · holdout MAE %s vs naive %s (%+.0f%%) · r %s',
                        round($calibration['factor'], 3),
                        round($calibration['ci'][0], 3),
                        round($calibration['ci'][1], 3),
                        $calibration['overlap_days'],
                        round($validation['harmonized']['mae'], 1),
                        round($validation['naive']['mae'], 1),
                        $validation['mae_improvement'] * 100,
                        $validation['correlation'],
                    ),
                );
            }

            foreach ($result['alerts'] as $period => $count) {
                $this->components->twoColumnDetail("  anomalies ({$period})", number_format($count));
            }
        }

        return self::SUCCESS;
    }
}
