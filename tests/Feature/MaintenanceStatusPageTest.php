<?php

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\MonitorGroup;

use function Pest\Laravel\get;

it('shows an under maintenance indicator on the html status page', function () {
    $group = MonitorGroup::factory()->create(['slug' => 'client-a']);
    $monitor = Monitor::factory()->up()->create([
        'name' => 'Client A website',
        'monitor_group_id' => $group->id,
    ]);
    MaintenanceWindow::factory()->active()->create()->monitors()->attach($monitor->id);

    get('/status/client-a')->assertOk()->assertSee('Under maintenance');
});

it('sets maintenance true on the json twin for a covered monitor', function () {
    $group = MonitorGroup::factory()->create(['slug' => 'client-a']);
    $monitor = Monitor::factory()->up()->create(['monitor_group_id' => $group->id]);
    MaintenanceWindow::factory()->active()->create()->groups()->attach($group->id);

    get('/status/client-a/json')
        ->assertOk()
        ->assertJsonPath('data.monitors.0.maintenance', true);
});

it('leaves maintenance false when no window is active', function () {
    $group = MonitorGroup::factory()->create(['slug' => 'client-a']);
    Monitor::factory()->up()->create(['monitor_group_id' => $group->id]);

    $response = get('/status/client-a/json')->assertOk();

    $response->assertJsonPath('data.monitors.0.maintenance', false);
    expect($response->getContent())->not->toContain('Under maintenance');
});
