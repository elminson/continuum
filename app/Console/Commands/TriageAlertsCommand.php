<?php

namespace App\Console\Commands;

use App\Actions\Insights\TriageAlert;
use App\Models\Alert;
use App\Models\AlertTriage;
use Illuminate\Console\Command;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Throwable;

class TriageAlertsCommand extends Command
{
    protected $signature = 'continuum:triage-alerts
        {--limit= : Most alerts to send to the model in one run (default: continuum.analyst.batch)}
        {--force : Re-read alerts even when their numbers have not changed}';

    protected $description = 'Ask the alert analyst (self-hosted model) for an advisory reading of new or changed unusual periods';

    public function handle(TriageAlert $triage): int
    {
        $alerts = Alert::query()->with('region')->get();
        AlertTriage::attachTo($alerts);

        // Public areas first, then the strongest departures.
        $pending = $alerts
            ->filter(fn (Alert $alert) => $this->option('force') || $alert->getRelation('triage')?->input_hash !== $triage->hash($alert))
            ->sortByDesc(fn (Alert $alert) => [$alert->region->is_preset ? 1 : 0, $alert->z_score])
            ->take((int) ($this->option('limit') ?? config('continuum.analyst.batch', 60)));

        if ($pending->isEmpty()) {
            $this->components->info('Every unusual period already has a current reading.');

            return self::SUCCESS;
        }

        $done = 0;

        foreach ($pending as $alert) {
            try {
                $triage($alert);
                $done++;
            } catch (ProviderConnectionException $exception) {
                // The model runs on another machine behind a tunnel; when it is
                // away, stop quietly and pick up on the next scheduled run.
                $this->components->warn('Alert analyst unreachable, will retry on the next run: '.$exception->getMessage());

                break;
            } catch (Throwable $exception) {
                $this->components->error("Alert {$alert->id}: ".$exception->getMessage());
            }
        }

        $this->components->info("Read {$done} of {$pending->count()} unusual period(s).");

        return self::SUCCESS;
    }
}
