<?php

use App\Models\CheckRollup;
use App\Models\Monitor;
use App\Models\MonitorGroup;

use function Pest\Laravel\get;

it('renders a public status page with monitor names but never urls', function () {
    $group = MonitorGroup::factory()->create(['name' => 'Client A', 'slug' => 'client-a']);
    $monitor = Monitor::factory()->up()->create([
        'name' => 'Client A website',
        'url' => 'https://secret-internal.client-a.example/admin',
        'monitor_group_id' => $group->id,
    ]);
    CheckRollup::factory()->for($monitor)->create([
        'period' => 'hour',
        'period_start' => now()->subHours(2)->startOfHour(),
        'checks_total' => 12,
        'checks_failed' => 0,
    ]);

    $body = get('/status/client-a')->assertOk()->getContent();

    expect($body)->toContain('Client A website');
    expect($body)->toContain('All systems operational');
    expect($body)->not->toContain('secret-internal.client-a.example');
});

it('returns 404 for a non-public group', function () {
    MonitorGroup::factory()->private()->create(['slug' => 'hidden']);

    get('/status/hidden')->assertNotFound();
});

it('returns 404 for an unknown slug', function () {
    get('/status/nope')->assertNotFound();
});

it('excludes paused monitors from the page', function () {
    $group = MonitorGroup::factory()->create(['slug' => 'client-b']);
    Monitor::factory()->up()->create(['name' => 'Active site', 'monitor_group_id' => $group->id]);
    Monitor::factory()->paused()->create(['name' => 'Paused site', 'monitor_group_id' => $group->id]);

    $body = get('/status/client-b')->assertOk()->getContent();

    expect($body)->toContain('Active site');
    expect($body)->not->toContain('Paused site');
});

it('reports down when any monitor is down', function () {
    $group = MonitorGroup::factory()->create(['slug' => 'client-c']);
    Monitor::factory()->up()->create(['monitor_group_id' => $group->id]);
    Monitor::factory()->down()->create(['monitor_group_id' => $group->id]);

    get('/status/client-c')->assertOk()->assertSee('Some systems are down');
});
