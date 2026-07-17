<?php

use App\Models\CheckRollup;
use App\Support\Chart;
use Illuminate\Support\Collection;

function rollup(array $attributes): CheckRollup
{
    return CheckRollup::factory()->makeOne($attributes);
}

it('renders an empty state when there is no response-time data', function () {
    expect(Chart::responseTime(new Collection, '24 hours'))->toContain('Not enough data yet');
});

it('maps response-time values into an accessible svg', function () {
    $rollups = collect([
        rollup(['period_start' => now()->subHours(2), 'avg_response_time_ms' => 120, 'checks_failed' => 0]),
        rollup(['period_start' => now()->subHour(), 'avg_response_time_ms' => 420, 'checks_failed' => 0]),
    ]);

    $svg = Chart::responseTime($rollups, '24 hours');

    expect($svg)->toContain('role="img"');
    expect($svg)->toContain('<polyline');
    expect($svg)->toContain('range 120-420 ms');
    expect($svg)->toContain('aria-label');
});

it('marks buckets that had failures on the response chart', function () {
    $rollups = collect([
        rollup(['period_start' => now()->subHour(), 'avg_response_time_ms' => 200, 'checks_failed' => 3]),
    ]);

    expect(Chart::responseTime($rollups, '24 hours'))->toContain('<circle');
});

it('renders an uptime bar summarizing the window', function () {
    $rollups = collect([
        rollup(['period_start' => now()->subDays(2), 'checks_total' => 100, 'checks_failed' => 0]),
        rollup(['period_start' => now()->subDay(), 'checks_total' => 100, 'checks_failed' => 5]),
    ]);

    $svg = Chart::uptimeBar($rollups, '30 days');

    expect($svg)->toContain('<rect');
    expect($svg)->toContain('97.5% across 2 buckets');
});

it('renders an empty uptime bar without data', function () {
    expect(Chart::uptimeBar(new Collection, '30 days'))->toContain('Not enough data yet');
});
