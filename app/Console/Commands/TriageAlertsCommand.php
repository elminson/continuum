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
        {--limit=500 : Most alerts to send to the model in one run}
        {--force : Re-read alerts even when their numbers have not changed}';

    protected $description = 'Ask the decision model (Laya) for an advisory reading of new or changed unusual periods';

    public function handle(TriageAlert $triage): int
    {
        $alerts = Alert::query()->with('region')->orderByDesc('z_score')->get();
        AlertTriage::attachTo($alerts);

        $pending = $alerts
            ->filter(fn (Alert $alert) => $this->option('force') || $alert->getRelation('triage')?->input_hash !== $triage->hash($alert))
            ->take((int) $this->option('limit'));

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
                $this->components->warn('Decision model unreachable, will retry on the next run: '.$exception->getMessage());

                break;
            } catch (Throwable $exception) {
                $this->components->error("Alert {$alert->id}: ".$exception->getMessage());
            }
        }

        $this->components->info("Read {$done} of {$pending->count()} unusual period(s).");

        return self::SUCCESS;
    }
}
