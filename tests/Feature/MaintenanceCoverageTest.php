<?php

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\MonitorGroup;

it('detects a monitor targeted directly by an active window', function () {
    $monitor = Monitor::factory()->create();
    MaintenanceWindow::factory()->active()->create()->monitors()->attach($monitor->id);

    expect($monitor->isUnderMaintenance())->toBeTrue();
});

it('detects a monitor covered through its group', function () {
    $group = MonitorGroup::factory()->create();
    $monitor = Monitor::factory()->create(['monitor_group_id' => $group->id]);
    MaintenanceWindow::factory()->active()->create()->groups()->attach($group->id);

    expect($monitor->isUnderMaintenance())->toBeTrue();
});

it('ignores windows that have not started or have already ended', function () {
    $monitor = Monitor::factory()->create();
    MaintenanceWindow::factory()->upcoming()->create()->monitors()->attach($monitor->id);
    MaintenanceWindow::factory()->past()->create()->monitors()->attach($monitor->id);

    expect($monitor->isUnderMaintenance())->toBeFalse();
});

it('does not cover a monitor targeted by no window', function () {
    $covered = Monitor::factory()->create();
    $other = Monitor::factory()->create();
    MaintenanceWindow::factory()->active()->create()->monitors()->attach($covered->id);

    expect($other->isUnderMaintenance())->toBeFalse();
});

it('scopes active windows by instant', function () {
    MaintenanceWindow::factory()->active()->create();
    MaintenanceWindow::factory()->past()->create();
    MaintenanceWindow::factory()->upcoming()->create();

    expect(MaintenanceWindow::active()->count())->toBe(1);
});
