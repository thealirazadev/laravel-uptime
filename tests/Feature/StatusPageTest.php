<?php

use App\Models\CheckRollup;
use App\Models\Monitor;
use App\Models\MonitorGroup;
use Illuminate\Support\Facades\Cache;

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

it('renders a public group with no active monitors without error', function () {
    MonitorGroup::factory()->create(['name' => 'Brand new', 'slug' => 'empty']);

    $body = get('/status/empty')->assertOk()->getContent();

    expect($body)->toContain('No monitors are being reported for this page yet.');
    expect($body)->toContain('Status not yet determined');
});

it('serves the json twin for an empty group as unknown with empty collections', function () {
    MonitorGroup::factory()->create(['slug' => 'empty']);

    get('/status/empty/json')
        ->assertOk()
        ->assertJsonPath('data.overall', 'unknown')
        ->assertJsonPath('data.monitors', [])
        ->assertJsonPath('data.incidents', []);
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

it('serves the json twin in the documented contract shape', function () {
    $group = MonitorGroup::factory()->create(['name' => 'Client A', 'slug' => 'client-a']);
    $monitor = Monitor::factory()->up()->create([
        'name' => 'Client A website',
        'url' => 'https://secret.client-a.example',
        'monitor_group_id' => $group->id,
    ]);
    // 24 h: 200 hourly checks, 1 failed -> 99.50% uptime.
    CheckRollup::factory()->for($monitor)->create([
        'period' => 'hour',
        'period_start' => now()->subHours(2)->startOfHour(),
        'checks_total' => 200,
        'checks_failed' => 1,
        'avg_response_time_ms' => 180,
    ]);

    $response = get('/status/client-a/json')->assertOk();

    $response->assertJsonPath('data.group.slug', 'client-a');
    $response->assertJsonPath('data.overall', 'operational');
    $response->assertJsonPath('data.monitors.0.name', 'Client A website');
    $response->assertJsonPath('data.monitors.0.status', 'up');
    $response->assertJsonPath('data.monitors.0.uptime.day', 99.5);
    $response->assertJsonPath('data.monitors.0.avg_response_time_ms.day', 180);

    expect($response->getContent())->not->toContain('secret.client-a.example');
});

it('returns null uptime windows without rollup data', function () {
    $group = MonitorGroup::factory()->create(['slug' => 'fresh']);
    Monitor::factory()->create(['monitor_group_id' => $group->id, 'status' => 'unknown']);

    get('/status/fresh/json')
        ->assertOk()
        ->assertJsonPath('data.monitors.0.uptime.day', null)
        ->assertJsonPath('data.overall', 'unknown');
});

it('returns the error envelope for an unknown json slug', function () {
    get('/status/nope/json')
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'not_found', 'message' => 'Status page not found.']]);
});

it('returns the same 404 envelope for a non-public json slug', function () {
    MonitorGroup::factory()->private()->create(['slug' => 'hidden']);

    get('/status/hidden/json')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('caches the status payload for about a minute', function () {
    $group = MonitorGroup::factory()->create(['slug' => 'cached']);
    $monitor = Monitor::factory()->up()->create(['monitor_group_id' => $group->id]);

    get('/status/cached/json')->assertJsonPath('data.overall', 'operational');

    // Mutate underneath the cache (query builder bypasses mass-assignment guards);
    // the cached response should not change yet.
    Monitor::whereKey($monitor->id)->update(['status' => 'down']);
    get('/status/cached/json')->assertJsonPath('data.overall', 'operational');

    // Once the cache expires (simulated by clearing it), fresh data is served.
    Cache::flush();
    get('/status/cached/json')->assertJsonPath('data.overall', 'down');
});
