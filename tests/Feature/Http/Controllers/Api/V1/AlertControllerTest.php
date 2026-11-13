<?php

use App\Enums\CalendarPeriod;
use App\Models\Alert;
use App\Models\Region;
use Illuminate\Support\Facades\DB;

it('lists the alerts of a region and filters by period', function () {
    $region = Region::factory()->preset()->create(['slug' => 'gran-chaco']);
    Alert::factory()->for($region)->create(['period' => CalendarPeriod::Week, 'message' => 'weekly']);
    Alert::factory()->for($region)->create(['period' => CalendarPeriod::Month, 'period_start' => '2026-09-01', 'message' => 'monthly']);

    $this->getJson('/api/v1/regions/gran-chaco/alerts')->assertOk()->assertJsonCount(2, 'data');

    $this->getJson('/api/v1/regions/gran-chaco/alerts?period=month')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.message', 'monthly')
        ->assertJsonPath('data.0.severity', 'warning');
});

it('returns 422 for an unknown period', function () {
    Region::factory()->preset()->create(['slug' => 'gran-chaco']);

    $this->getJson('/api/v1/regions/gran-chaco/alerts?period=hour')->assertUnprocessable();
});

it('lists alerts in the same number of queries however many there are', function () {
    $region = Region::factory()->preset()->create(['slug' => 'gran-chaco']);

    $queries = function (int $alerts) use ($region): int {
        Alert::query()->delete();
        Alert::factory()->for($region)->count($alerts)->sequence(fn ($sequence) => ['period_start' => now()->subDays($sequence->index + 1)->toDateString()])->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/regions/gran-chaco/alerts')->assertOk()->assertJsonCount($alerts, 'data');
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($queries(20))->toBe($queries(2));
});
