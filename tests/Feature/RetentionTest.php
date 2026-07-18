<?php

use App\Models\Check;
use App\Models\CheckRollup;
use App\Models\Monitor;

use function Pest\Laravel\artisan;

it('prunes raw checks older than the raw retention window', function () {
    $monitor = Monitor::factory()->create();
    $old = Check::factory()->for($monitor)->create(['checked_at' => now()->subDays(8)]);
    $recent = Check::factory()->for($monitor)->create(['checked_at' => now()->subDays(3)]);

    artisan('uptime:prune')->assertExitCode(0);

    expect(Check::find($old->id))->toBeNull();
    expect(Check::find($recent->id))->not->toBeNull();
});

it('prunes hourly rollups older than the hourly retention window', function () {
    $monitor = Monitor::factory()->create();
    $old = CheckRollup::factory()->for($monitor)->create([
        'period' => 'hour',
        'period_start' => now()->subDays(120)->startOfHour(),
    ]);
    $recent = CheckRollup::factory()->for($monitor)->create([
        'period' => 'hour',
        'period_start' => now()->subDays(30)->startOfHour(),
    ]);

    artisan('uptime:prune');

    expect(CheckRollup::find($old->id))->toBeNull();
    expect(CheckRollup::find($recent->id))->not->toBeNull();
});

it('prunes daily rollups older than the daily retention window but keeps hourly untouched by day rules', function () {
    $monitor = Monitor::factory()->create();
    $oldDay = CheckRollup::factory()->for($monitor)->day()->create([
        'period_start' => now()->subDays(400)->startOfDay(),
    ]);
    $recentDay = CheckRollup::factory()->for($monitor)->day()->create([
        'period_start' => now()->subDays(200)->startOfDay(),
    ]);

    artisan('uptime:prune');

    expect(CheckRollup::find($oldDay->id))->toBeNull();
    expect(CheckRollup::find($recentDay->id))->not->toBeNull();
});

it('honors configured retention windows', function () {
    config(['uptime.raw_retention_days' => 2]);
    $monitor = Monitor::factory()->create();
    $check = Check::factory()->for($monitor)->create(['checked_at' => now()->subDays(3)]);

    artisan('uptime:prune');

    expect(Check::find($check->id))->toBeNull();
});
