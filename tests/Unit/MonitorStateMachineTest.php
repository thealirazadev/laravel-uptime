<?php

use App\Models\Monitor;
use App\Support\CheckOutcome;
use Illuminate\Support\Carbon;

function ok(): CheckOutcome
{
    return CheckOutcome::success(200, 120);
}

function fail(): CheckOutcome
{
    return CheckOutcome::failure('connection_failed');
}

it('does not flip status on a single failure below the threshold', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'unknown']);

    $monitor->applyCheckResult(fail());

    expect($monitor->status)->toBe('unknown');
    expect($monitor->consecutive_failures)->toBe(1);
    expect($monitor->consecutive_successes)->toBe(0);
    expect($monitor->first_failed_at)->not->toBeNull();
});

it('flips to down at the threshold with first_failed_at at the streak start', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);

    Carbon::setTestNow('2026-07-18 09:00:00');
    $monitor->applyCheckResult(fail());
    expect($monitor->status)->toBe('up');
    $streakStart = $monitor->first_failed_at->copy();

    Carbon::setTestNow('2026-07-18 09:01:00');
    $monitor->applyCheckResult(fail());

    expect($monitor->status)->toBe('down');
    expect($monitor->consecutive_failures)->toBe(2);
    expect($monitor->first_failed_at->equalTo($streakStart))->toBeTrue();
    expect($streakStart->toDateTimeString())->toBe('2026-07-18 09:00:00');

    Carbon::setTestNow();
});

it('resets the failure streak on any success', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);

    $monitor->applyCheckResult(fail());
    $monitor->applyCheckResult(ok());

    expect($monitor->consecutive_failures)->toBe(0);
    expect($monitor->consecutive_successes)->toBe(1);
    expect($monitor->first_failed_at)->toBeNull();
    expect($monitor->last_error)->toBeNull();
    expect($monitor->status)->toBe('up');
});

it('produces no transition for a flapping sequence below the threshold', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'unknown']);

    foreach ([fail(), ok(), fail(), ok()] as $outcome) {
        $monitor->applyCheckResult($outcome);
        expect($monitor->status)->toBe('unknown');
    }
});

it('confirms unknown to up after the threshold silently', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'unknown']);

    $monitor->applyCheckResult(ok());
    expect($monitor->status)->toBe('unknown');

    $monitor->applyCheckResult(ok());
    expect($monitor->status)->toBe('up');
});

it('recovers from down to up after the threshold of successes', function () {
    $monitor = Monitor::factory()->create([
        'confirmation_threshold' => 2,
        'status' => 'down',
        'consecutive_failures' => 2,
    ]);

    $monitor->applyCheckResult(ok());
    expect($monitor->status)->toBe('down');

    $monitor->applyCheckResult(ok());
    expect($monitor->status)->toBe('up');
});

it('honors the threshold edge N-1 versus N', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 3, 'status' => 'up']);

    $monitor->applyCheckResult(fail());
    $monitor->applyCheckResult(fail());
    expect($monitor->status)->toBe('up');

    $monitor->applyCheckResult(fail());
    expect($monitor->status)->toBe('down');
});
