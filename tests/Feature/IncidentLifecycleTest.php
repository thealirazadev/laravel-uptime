<?php

use App\Models\Incident;
use App\Models\Monitor;
use App\Support\CheckOutcome;
use Illuminate\Support\Carbon;

function down(): CheckOutcome
{
    return CheckOutcome::failure('status_mismatch:503', 503, 90);
}

function up(): CheckOutcome
{
    return CheckOutcome::success(200, 110);
}

it('opens exactly one incident at the threshold with started_at at the first failure', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);

    Carbon::setTestNow('2026-07-18 09:41:00');
    $monitor->applyCheckResult(down());
    expect($monitor->incidents()->count())->toBe(0);

    Carbon::setTestNow('2026-07-18 09:42:00');
    $monitor->applyCheckResult(down());

    $incidents = Incident::all();
    expect($incidents)->toHaveCount(1);

    $incident = $incidents->first();
    expect($incident->started_at->toDateTimeString())->toBe('2026-07-18 09:41:00');
    expect($incident->closed_at)->toBeNull();
    expect($incident->summary)->toBe('status_mismatch:503');
    expect($incident->events()->where('type', 'opened')->count())->toBe(1);

    Carbon::setTestNow();
});

it('does not open a second incident while already down', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);

    foreach (range(1, 10) as $i) {
        $monitor->applyCheckResult(down());
    }

    expect(Incident::count())->toBe(1);
    expect($monitor->openIncident())->not->toBeNull();
});

it('closes the incident on confirmed recovery with a closed event', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);

    $monitor->applyCheckResult(down());
    $monitor->applyCheckResult(down());
    $incident = $monitor->openIncident();
    expect($incident)->not->toBeNull();

    $monitor->applyCheckResult(up());
    expect($monitor->openIncident())->not->toBeNull();

    $monitor->applyCheckResult(up());

    $incident->refresh();
    expect($monitor->status)->toBe('up');
    expect($incident->closed_at)->not->toBeNull();
    expect($incident->events()->where('type', 'closed')->count())->toBe(1);
    expect($monitor->openIncident())->toBeNull();
});

it('opens zero incidents for a flapping sequence below the threshold', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'unknown']);

    foreach ([down(), up(), down(), up(), down(), up()] as $outcome) {
        $monitor->applyCheckResult($outcome);
    }

    expect(Incident::count())->toBe(0);
});
