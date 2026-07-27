<?php

use App\Jobs\SendAlert;
use App\Models\AlertChannel;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\MonitorGroup;
use App\Support\CheckOutcome;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function mwFail(): CheckOutcome
{
    return CheckOutcome::failure('connection_failed');
}

function mwOk(): CheckOutcome
{
    return CheckOutcome::success(200, 100);
}

it('records checks but opens no incident and sends no alert inside a window', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);
    $monitor->channels()->attach(AlertChannel::factory()->create()->id);
    MaintenanceWindow::factory()->active()->create()->monitors()->attach($monitor->id);

    $monitor->applyCheckResult(mwFail());
    $monitor->applyCheckResult(mwFail());

    expect(Incident::count())->toBe(0);
    expect($monitor->fresh()->status)->toBe('up');
    Queue::assertNothingPushed();
});

it('opens an incident and alerts when no window is active', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);
    $monitor->channels()->attach(AlertChannel::factory()->create()->id);
    // A window that is already over must not suppress.
    MaintenanceWindow::factory()->past()->create()->monitors()->attach($monitor->id);

    $monitor->applyCheckResult(mwFail());
    $monitor->applyCheckResult(mwFail());

    expect(Incident::count())->toBe(1);
    expect($monitor->fresh()->status)->toBe('down');
    Queue::assertPushed(SendAlert::class, 1);
});

it('resumes alerting once the window ends', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);
    $monitor->channels()->attach(AlertChannel::factory()->create()->id);
    $window = MaintenanceWindow::factory()->active()->create();
    $window->monitors()->attach($monitor->id);

    $monitor->applyCheckResult(mwFail());
    $monitor->applyCheckResult(mwFail());
    expect(Incident::count())->toBe(0);

    // Window ends; the next confirmed failure now opens exactly one incident.
    $window->update(['ends_at' => now()->subMinute()]);
    $monitor->applyCheckResult(mwFail());

    expect(Incident::count())->toBe(1);
    expect($monitor->fresh()->status)->toBe('down');
    Queue::assertPushed(SendAlert::class, 1);
});

it('preserves an already-open incident through a window without a recovery alert', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);
    $monitor->channels()->attach(AlertChannel::factory()->create()->id);

    // Open an incident before any window exists.
    $monitor->applyCheckResult(mwFail());
    $monitor->applyCheckResult(mwFail());
    $incident = $monitor->openIncident();
    expect($incident)->not->toBeNull();
    Queue::assertPushed(SendAlert::class, 1);

    // A window becomes active; the monitor recovers during it.
    MaintenanceWindow::factory()->active()->create()->monitors()->attach($monitor->id);
    $monitor->applyCheckResult(mwOk());
    $monitor->applyCheckResult(mwOk());

    // Incident stays open and no recovery alert was sent while suppressed.
    expect($monitor->openIncident())->not->toBeNull();
    expect($monitor->fresh()->status)->toBe('down');
    Queue::assertPushed(SendAlert::class, 1);
});

it('suppresses a monitor covered through its group', function () {
    $group = MonitorGroup::factory()->create();
    $monitor = Monitor::factory()->create([
        'confirmation_threshold' => 2,
        'status' => 'up',
        'monitor_group_id' => $group->id,
    ]);
    $monitor->channels()->attach(AlertChannel::factory()->create()->id);
    MaintenanceWindow::factory()->active()->create()->groups()->attach($group->id);

    $monitor->applyCheckResult(mwFail());
    $monitor->applyCheckResult(mwFail());

    expect(Incident::count())->toBe(0);
    Queue::assertNothingPushed();
});
