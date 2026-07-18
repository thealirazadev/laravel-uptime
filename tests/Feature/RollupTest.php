<?php

use App\Models\Check;
use App\Models\CheckRollup;
use App\Models\Monitor;
use Illuminate\Support\Carbon;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Carbon::setTestNow('2026-07-18 10:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function seedHour(Monitor $monitor, Carbon $hour, int $ok, int $failed, array $responseTimes = []): void
{
    for ($i = 1; $i <= $ok; $i++) {
        Check::factory()->for($monitor)->create([
            'ok' => true,
            'response_time_ms' => $responseTimes[$i - 1] ?? 100,
            'checked_at' => $hour->copy()->addMinutes($i),
        ]);
    }

    for ($i = 1; $i <= $failed; $i++) {
        Check::factory()->for($monitor)->failed()->create([
            'checked_at' => $hour->copy()->addMinutes(30 + $i),
        ]);
    }
}

it('aggregates completed hours matching the raw rows', function () {
    $monitor = Monitor::factory()->create();
    $hour = Carbon::parse('2026-07-18 09:00:00');
    seedHour($monitor, $hour, ok: 8, failed: 2, responseTimes: [100, 200, 300, 100, 100, 100, 100, 100]);

    artisan('uptime:rollup hour')->assertExitCode(0);

    $rollup = CheckRollup::where('period', 'hour')->sole();
    expect($rollup->checks_total)->toBe(10);
    expect($rollup->checks_failed)->toBe(2);
    expect($rollup->min_response_time_ms)->toBe(100);
    expect($rollup->max_response_time_ms)->toBe(300);
    expect($rollup->avg_response_time_ms)->toBe(138); // round((100*5+200+300+100)/8)
});

it('does not roll up the current incomplete hour', function () {
    $monitor = Monitor::factory()->create();
    // A check in the current (incomplete) hour must be excluded.
    Check::factory()->for($monitor)->create(['checked_at' => now()->startOfHour()->addMinutes(5)]);

    artisan('uptime:rollup hour');

    expect(CheckRollup::count())->toBe(0);
});

it('is idempotent across repeated runs', function () {
    $monitor = Monitor::factory()->create();
    seedHour($monitor, Carbon::parse('2026-07-18 09:00:00'), ok: 5, failed: 1);

    artisan('uptime:rollup hour');
    artisan('uptime:rollup hour');

    expect(CheckRollup::where('period', 'hour')->count())->toBe(1);
    expect(CheckRollup::where('period', 'hour')->sole()->checks_total)->toBe(6);
});

it('rolls hourly rollups into a daily rollup', function () {
    $monitor = Monitor::factory()->create();
    $yesterday = Carbon::parse('2026-07-17 00:00:00');
    seedHour($monitor, $yesterday->copy()->addHours(1), ok: 10, failed: 0, responseTimes: array_fill(0, 10, 100));
    seedHour($monitor, $yesterday->copy()->addHours(2), ok: 8, failed: 2, responseTimes: array_fill(0, 8, 300));

    artisan('uptime:rollup hour');
    artisan('uptime:rollup day');

    $day = CheckRollup::where('period', 'day')->sole();
    expect($day->period_start->toDateString())->toBe('2026-07-17');
    expect($day->checks_total)->toBe(20);
    expect($day->checks_failed)->toBe(2);
    expect($day->min_response_time_ms)->toBe(100);
    expect($day->max_response_time_ms)->toBe(300);
    // Daily avg weights each hour's average by its checks_total: (100*10 + 300*10) / 20 = 200.
    expect($day->avg_response_time_ms)->toBe(200);
});

it('rejects an invalid period', function () {
    artisan('uptime:rollup week')->assertExitCode(1);
});
