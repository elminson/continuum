<?php

use App\Actions\Insights\TriageAlert;
use App\Enums\AlertSeverity;
use App\Models\Alert;
use App\Models\AlertTriage;
use App\Models\Region;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\ClassificationPrompt;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Laravel\Ai\Responses\Data\ScoreAnswer;

function fakeLaya(): void
{
    Classification::fake(fn (ClassificationPrompt $prompt) => [
        'cause' => new ChoiceAnswer('data_gap', ['seasonal' => 0.05, 'outbreak' => 0.03, 'data_gap' => 0.81, 'sensor' => 0.11], confidence: 0.74),
        'artifact' => new BooleanAnswer(0.74),
        'concern' => new ScoreAnswer(0.4, [0.62, 0.36, 0.02], AlertTriage::CONCERN_LEVELS),
    ]);
}

it('stores an advisory reading of a flagged period', function () {
    fakeLaya();
    $region = Region::factory()->preset()->create(['name' => 'Gran Chaco']);
    $alert = Alert::factory()->for($region)->create(['severity' => AlertSeverity::Critical, 'observed' => 2751, 'expected' => 0, 'spread' => 1, 'z_score' => 2751, 'climatology_years' => 25]);

    $triage = app(TriageAlert::class)($alert);

    expect($triage->cause)->toBe('data_gap')
        ->and($triage->cause_probabilities['data_gap'])->toBe(0.81)
        ->and($triage->artifact_probability)->toBe(0.74)
        ->and($triage->concern_level)->toBe(0)
        ->and($triage->input_hash)->toBe(app(TriageAlert::class)->hash($alert));

    Classification::assertClassified(fn (ClassificationPrompt $prompt) => $prompt->contains('Gran Chaco')
        && $prompt->contains('2,751')
        && $prompt->asks('cause') && $prompt->asks('artifact') && $prompt->asks('concern'));
});

it('only re-reads alerts whose numbers changed, and keeps readings across alert rebuilds', function () {
    fakeLaya();
    $region = Region::factory()->preset()->create();
    $alert = Alert::factory()->for($region)->create(['observed' => 100]);

    $this->artisan('continuum:triage-alerts')->expectsOutputToContain('Read 1 of 1')->assertSuccessful();
    $this->artisan('continuum:triage-alerts')->expectsOutputToContain('already has a current reading')->assertSuccessful();

    // Harmonization deletes and re-inserts alerts; the same period keeps its reading.
    $copy = $alert->replicate();
    $alert->delete();
    $copy->save();
    $this->artisan('continuum:triage-alerts')->expectsOutputToContain('already has a current reading')->assertSuccessful();

    $copy->update(['observed' => 250]);
    $this->artisan('continuum:triage-alerts')->expectsOutputToContain('Read 1 of 1')->assertSuccessful();

    expect(AlertTriage::query()->count())->toBe(1);
});

it('stops quietly when the model cannot be reached', function () {
    config()->set('ai.providers.laya-test', ['driver' => 'typesafe', 'key' => 'x', 'url' => 'http://127.0.0.1:9/v1']);
    config()->set('ai.default_for_classification', ['laya-test']);
    Alert::factory()->for(Region::factory()->preset())->create();

    $this->artisan('continuum:triage-alerts')
        ->expectsOutputToContain('unreachable')
        ->assertSuccessful();

    expect(AlertTriage::query()->count())->toBe(0);
});

it('includes the reading in the public alerts API', function () {
    fakeLaya();
    $region = Region::factory()->preset()->create();
    $alert = Alert::factory()->for($region)->create();
    app(TriageAlert::class)($alert);

    $this->getJson(route('api.v1.regions.alerts', $region))
        ->assertOk()
        ->assertJsonPath('data.0.triage.cause', 'data_gap')
        ->assertJsonPath('data.0.triage.artifact_probability', 0.74)
        ->assertJsonPath('data.0.triage.concern_label', 'No action needed');
});
