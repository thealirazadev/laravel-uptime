<?php

use App\Models\Check;
use App\Models\CheckRollup;
use App\Models\Monitor;
use Illuminate\Support\Carbon;

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

it('never prunes part of an hour that the hourly rollup still rebuilds', function () {
    $monitor = Monitor::factory()->create();
    Check::factory()->for($monitor)->create(['checked_at' => '2026-07-11 00:10:00']);
    Check::factory()->for($monitor)->create(['checked_at' => '2026-07-11 00:30:00']);

    // Daily maintenance sequence at 00:20, seven days later.
    Carbon::setTestNow('2026-07-18 00:20:00');
    artisan('uptime:rollup hour');
    artisan('uptime:prune');

    // The next hourly run re-aggregates whatever raw rows are left.
    Carbon::setTestNow('2026-07-18 01:05:00');
    artisan('uptime:rollup hour');

    $rollup = CheckRollup::query()
        ->where('period', 'hour')
        ->where('period_start', '2026-07-11 00:00:00')
        ->sole();
    expect($rollup->checks_total)->toBe(2);

    Carbon::setTestNow();
});

it('never prunes part of a day that the daily rollup still rebuilds', function () {
    $monitor = Monitor::factory()->create();
    CheckRollup::factory()->for($monitor)->create([
        'period' => 'hour',
        'period_start' => '2026-04-19 00:00:00',
        'checks_total' => 60,
        'checks_failed' => 0,
    ]);
    CheckRollup::factory()->for($monitor)->create([
        'period' => 'hour',
        'period_start' => '2026-04-19 05:00:00',
        'checks_total' => 60,
        'checks_failed' => 0,
    ]);

    // 90 days later, at the 00:20 prune.
    Carbon::setTestNow('2026-07-18 00:20:00');
    artisan('uptime:rollup day');
    artisan('uptime:prune');
    artisan('uptime:rollup day');

    $day = CheckRollup::query()
        ->where('period', 'day')
        ->where('period_start', '2026-04-19 00:00:00')
        ->sole();
    expect($day->checks_total)->toBe(120);

    Carbon::setTestNow();
});
