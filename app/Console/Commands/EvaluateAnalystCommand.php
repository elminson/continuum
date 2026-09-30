<?php

namespace App\Console\Commands;

use App\Ai\Evals\AlertAnalystBenchmark;
use Illuminate\Console\Command;

class EvaluateAnalystCommand extends Command
{
    protected $signature = 'continuum:evaluate-analyst';

    protected $description = 'Run the alert analyst against its labelled benchmark and store the score';

    public function handle(AlertAnalystBenchmark $benchmark): int
    {
        $evaluation = $benchmark->run(function (array $result): void {
            $mark = $result['passed'] ? '<fg=green>pass</>' : '<fg=red>fail</>';
            $got = $result['got'] === null ? 'error: '.$result['error'] : sprintf('%s / %s / %s', $result['got']['baseline_reliable'] ? 'reliable' : 'unreliable', $result['got']['cause'], $result['got']['attention']);
            $this->line(sprintf('  %s  %-42s %s (%ss)', $mark, $result['name'], $got, $result['seconds']));
        });

        $scores = $evaluation->scores;
        $this->components->info(sprintf(
            '%s passed %d of %d cases. Baseline %s%%, cause %s%%, attention %s%%.',
            $evaluation->model,
            $scores['passed'],
            $evaluation->cases,
            round(100 * $scores['baseline_reliable']),
            round(100 * $scores['cause']),
            round(100 * $scores['attention']),
        ));

        return self::SUCCESS;
    }
}
