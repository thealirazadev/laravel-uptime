<?php

use App\Models\CheckRollup;
use App\Models\Monitor;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('shows a not-enough-data state on a brand-new monitor', function () {
    $monitor = Monitor::factory()->create();

    get("/monitors/{$monitor->id}")
        ->assertOk()
        ->assertSee('Not enough data yet');
});

it('renders rollup-backed charts when data exists', function () {
    $monitor = Monitor::factory()->create();
    CheckRollup::factory()->for($monitor)->create([
        'period' => 'hour',
        'period_start' => now()->subHours(2)->startOfHour(),
        'checks_total' => 60,
        'checks_failed' => 0,
        'avg_response_time_ms' => 150,
    ]);
    CheckRollup::factory()->for($monitor)->day()->create([
        'period_start' => now()->subDays(2)->startOfDay(),
        'checks_total' => 1440,
        'checks_failed' => 10,
        'avg_response_time_ms' => 175,
    ]);

    get("/monitors/{$monitor->id}")
        ->assertOk()
        ->assertSee('role="img"', false)
        ->assertSee('Average response time over the last 24 hours', false);
});
