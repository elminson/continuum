<?php

use Illuminate\Console\Scheduling\Schedule;

it('re-aggregates only the last few days on the scheduled harmonize', function () {
    $command = collect(app(Schedule::class)->events())
        ->map(fn ($event) => (string) $event->command)
        ->first(fn (string $command) => str_contains($command, 'continuum:harmonize'));

    expect($command)->toContain("--from='".now()->subDays(3)->toDateString()."'");
});
