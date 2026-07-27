<?php

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\MonitorGroup;

it('relates a window to both monitors and groups', function () {
    $window = MaintenanceWindow::factory()->create();
    $monitor = Monitor::factory()->create();
    $group = MonitorGroup::factory()->create();

    $window->monitors()->attach($monitor->id);
    $window->groups()->attach($group->id);

    expect($window->monitors)->toHaveCount(1)
        ->and($window->monitors->first()->id)->toBe($monitor->id)
        ->and($window->groups)->toHaveCount(1)
        ->and($window->groups->first()->id)->toBe($group->id);

    expect($monitor->maintenanceWindows)->toHaveCount(1)
        ->and($group->maintenanceWindows)->toHaveCount(1);
});
