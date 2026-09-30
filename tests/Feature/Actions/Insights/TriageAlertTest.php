<?php

use App\Actions\Insights\TriageAlert;
use App\Ai\Agents\AlertAnalyst;
use App\Ai\Evals\AlertAnalystBenchmark;
use App\Enums\AlertSeverity;
use App\Models\AiEvaluation;
use App\Models\Alert;
use App\Models\AlertTriage;
use App\Models\Region;
use Laravel\Ai\Prompts\AgentPrompt;

function fakeAnalyst(array $reading = []): void
{
    AlertAnalyst::fake(fn ($prompt) => array_merge([
        'baseline_reliable' => false,
        'artifact_risk' => 0.9,
        'cause' => 'data_gap',
        'attention' => 'no_action',
        'rationale' => 'Only one year of history, so the comparison cannot be trusted.',
    ], $reading));
}

it('stores the analyst reading of a flagged period', function () {
    fakeAnalyst();
    $region = Region::factory()->preset()->create(['name' => 'Gran Chaco']);
    $alert = Alert::factory()->for($region)->create(['severity' => AlertSeverity::Critical, 'observed' => 2751, 'expected' => 0, 'spread' => 1, 'z_score' => 2751, 'climatology_years' => 1]);

    $triage = app(TriageAlert::class)($alert);

    expect($triage->cause)->toBe('data_gap')
        ->and($triage->baseline_reliable)->toBeFalse()
        ->and($triage->artifact_probability)->toBe(0.9)
        ->and($triage->attention())->toBe('no_action')
        ->and($triage->rationale)->toContain('one year')
        ->and($triage->model)->toBe(config('continuum.analyst.model'))
        ->and($triage->input_hash)->toBe(app(TriageAlert::class)->hash($alert));

    AlertAnalyst::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Gran Chaco') && $prompt->contains('2,751'));
});

it('rejects answers outside the schema', function () {
    fakeAnalyst(['cause' => 'aliens']);
    $alert = Alert::factory()->for(Region::factory()->preset())->create();

    app(TriageAlert::class)($alert);
})->throws(UnexpectedValueException::class);

it('only re-reads alerts whose numbers changed, and keeps readings across alert rebuilds', function () {
    fakeAnalyst();
    $alert = Alert::factory()->for(Region::factory()->preset())->create(['observed' => 100]);

    $this->artisan('continuum:triage-alerts')->expectsOutputToContain('Read 1 of 1')->assertSuccessful();
    $this->artisan('continuum:triage-alerts')->expectsOutputToContain('already has a current reading')->assertSuccessful();

    $copy = $alert->replicate();
    $alert->delete();
    $copy->save();
    $this->artisan('continuum:triage-alerts')->expectsOutputToContain('already has a current reading')->assertSuccessful();

    $copy->update(['observed' => 250]);
    $this->artisan('continuum:triage-alerts')->expectsOutputToContain('Read 1 of 1')->assertSuccessful();

    expect(AlertTriage::query()->count())->toBe(1);
});

it('stops quietly when the model cannot be reached', function () {
    config()->set('ai.providers.ollama.url', 'http://127.0.0.1:9');
    Alert::factory()->for(Region::factory()->preset())->create();

    $this->artisan('continuum:triage-alerts')
        ->expectsOutputToContain('unreachable')
        ->assertSuccessful();

    expect(AlertTriage::query()->count())->toBe(0);
});

it('includes the reading, labelled as advisory, in the public alerts API', function () {
    fakeAnalyst(['baseline_reliable' => true, 'cause' => 'outbreak', 'attention' => 'act_now', 'artifact_risk' => 0.02]);
    $region = Region::factory()->preset()->create();
    app(TriageAlert::class)(Alert::factory()->for($region)->create());

    $this->getJson(route('api.v1.regions.alerts', $region))
        ->assertOk()
        ->assertJsonPath('data.0.triage.cause', 'outbreak')
        ->assertJsonPath('data.0.triage.attention', 'act_now')
        ->assertJsonPath('data.0.triage.attention_label', 'Act now')
        ->assertJsonPath('data.0.triage.baseline_reliable', true)
        ->assertJsonPath('data.0.triage.advisory', true);
});

it('grades the analyst on the labelled benchmark and stores the score', function () {
    // A model that always says "thin baseline" gets the three thin-baseline cases right and nothing else.
    fakeAnalyst();

    $this->artisan('continuum:evaluate-analyst')->expectsOutputToContain('passed 3 of 10')->assertSuccessful();

    $evaluation = AiEvaluation::latestFor(AlertAnalystBenchmark::SUITE);

    expect($evaluation->cases)->toBe(10)
        ->and($evaluation->scores['passed'])->toBe(3)
        ->and($evaluation->scores['baseline_reliable'])->toBe(0.3)
        ->and($evaluation->results[0]['state'])->toContain('Gran Chaco')
        ->and($evaluation->results[0]['passed'])->toBeTrue()
        ->and($evaluation->results[3]['passed'])->toBeFalse();
});
