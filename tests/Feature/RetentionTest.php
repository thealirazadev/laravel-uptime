<?php

use App\Models\Check;
use App\Models\CheckRollup;
use App\Models\Monitor;
use Illuminate\Support\Carbon;

use function Pest\Laravel\artisan;

afterEach(function () {
    Carbon::setTestNow();
});

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

it('keeps an hourly rollup complete when the prune cutoff falls inside that hour', function () {
    // The daily prune runs at 00:20, so a cutoff of exactly seven days lands
    // twenty minutes into an hour that the next hourly rollup re-aggregates.
    Carbon::setTestNow('2026-07-18 00:20:00');
    config(['uptime.raw_retention_days' => 7]);
    $monitor = Monitor::factory()->create();
    $hour = now()->subDays(7)->startOfHour();
    Check::factory()->for($monitor)->create(['checked_at' => $hour->copy()->addMinutes(5)]);
    Check::factory()->for($monitor)->create(['checked_at' => $hour->copy()->addMinutes(40)]);

    artisan('uptime:rollup hour');
    artisan('uptime:prune');
    artisan('uptime:rollup hour');

    $rollup = CheckRollup::where('period', 'hour')->sole();
    expect($rollup->period_start->equalTo($hour))->toBeTrue();
    expect($rollup->checks_total)->toBe(2);
});

it('keeps a daily rollup complete when the prune cutoff falls inside that day', function () {
    Carbon::setTestNow('2026-07-18 00:20:00');
    config(['uptime.hourly_retention_days' => 90]);
    $monitor = Monitor::factory()->create();
    $day = now()->subDays(90)->startOfDay();
    CheckRollup::factory()->for($monitor)->create([
        'period_start' => $day->copy(),
        'checks_total' => 12,
        'checks_failed' => 1,
    ]);
    CheckRollup::factory()->for($monitor)->create([
        'period_start' => $day->copy()->addHours(5),
        'checks_total' => 12,
        'checks_failed' => 0,
    ]);

    artisan('uptime:rollup day');
    artisan('uptime:prune');
    artisan('uptime:rollup day');

    $daily = CheckRollup::where('period', 'day')->sole();
    expect($daily->checks_total)->toBe(24);
    expect($daily->checks_failed)->toBe(1);
});

it('prunes raw checks only in whole hours and hourly rollups only in whole days', function () {
    Carbon::setTestNow('2026-07-18 00:20:00');
    config(['uptime.raw_retention_days' => 7, 'uptime.hourly_retention_days' => 90]);
    $monitor = Monitor::factory()->create();
    $hour = now()->subDays(7)->startOfHour();
    $before = Check::factory()->for($monitor)->create(['checked_at' => $hour->copy()->subMinute()]);
    $inside = Check::factory()->for($monitor)->create(['checked_at' => $hour->copy()->addMinutes(5)]);
    $dayStart = now()->subDays(90)->startOfDay();
    $oldHour = CheckRollup::factory()->for($monitor)->create(['period_start' => $dayStart->copy()->subHour()]);
    $keptHour = CheckRollup::factory()->for($monitor)->create(['period_start' => $dayStart->copy()]);

    artisan('uptime:prune');

    expect(Check::find($before->id))->toBeNull();
    expect(Check::find($inside->id))->not->toBeNull();
    expect(CheckRollup::find($oldHour->id))->toBeNull();
    expect(CheckRollup::find($keptHour->id))->not->toBeNull();
});
