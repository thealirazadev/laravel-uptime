<?php

use App\Models\Check;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAs(User::factory()->create());
});

function validMonitorPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Client A website',
        'url' => 'https://client-a.example',
        'interval_seconds' => 300,
        'timeout_seconds' => 10,
        'expected_status' => 200,
        'expected_keyword' => 'Welcome',
        'confirmation_threshold' => 2,
    ], $overrides);
}

it('requires authentication for monitor routes', function () {
    auth()->logout();

    get('/monitors')->assertRedirect('/login');
});

it('shows an empty state when there are no monitors', function () {
    get('/monitors')
        ->assertOk()
        ->assertSee('No monitors yet');
});

it('renders the create, show, and edit screens', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://client-a.example',
        'ssl_expires_at' => now()->addDays(20),
    ]);
    Check::factory()->for($monitor)->create();

    get('/monitors/create')->assertOk()->assertSee('Add monitor');
    get("/monitors/{$monitor->id}")->assertOk()->assertSee($monitor->name)->assertSee('SSL expires');
    get("/monitors/{$monitor->id}/edit")->assertOk()->assertSee('Edit monitor');
});

it('creates a monitor and marks it due immediately as unknown', function () {
    post('/monitors', validMonitorPayload())->assertRedirect();

    $monitor = Monitor::first();
    expect($monitor)->not->toBeNull();
    expect($monitor->status)->toBe('unknown');
    expect($monitor->is_active)->toBeTrue();
    expect($monitor->next_check_at)->not->toBeNull();
});

it('rejects a non-http url scheme', function () {
    post('/monitors', validMonitorPayload(['url' => 'ftp://client-a.example']))
        ->assertSessionHasErrors('url');

    expect(Monitor::count())->toBe(0);
});

it('rejects an unsupported interval', function () {
    post('/monitors', validMonitorPayload(['interval_seconds' => 120]))
        ->assertSessionHasErrors('interval_seconds');

    expect(Monitor::count())->toBe(0);
});

it('rejects an out-of-range timeout and threshold', function () {
    post('/monitors', validMonitorPayload(['timeout_seconds' => 40]))
        ->assertSessionHasErrors('timeout_seconds');

    post('/monitors', validMonitorPayload(['confirmation_threshold' => 20]))
        ->assertSessionHasErrors('confirmation_threshold');

    expect(Monitor::count())->toBe(0);
});

it('accepts a 2048-char url but rejects a longer one', function () {
    $base = 'https://client-a.example/';
    $ok = $base.str_repeat('a', 2048 - strlen($base));
    $tooLong = $base.str_repeat('a', 2049 - strlen($base));

    post('/monitors', validMonitorPayload(['url' => $ok]))->assertRedirect();
    expect(Monitor::count())->toBe(1);

    post('/monitors', validMonitorPayload(['name' => 'Other', 'url' => $tooLong]))
        ->assertSessionHasErrors('url');
    expect(Monitor::count())->toBe(1);
});

it('nulls an empty keyword', function () {
    post('/monitors', validMonitorPayload(['expected_keyword' => '   ']))->assertRedirect();

    expect(Monitor::first()->expected_keyword)->toBeNull();
});

it('updates a monitor and can pause it', function () {
    $monitor = Monitor::factory()->create();

    put("/monitors/{$monitor->id}", validMonitorPayload([
        'name' => 'Renamed',
        'is_active' => '0',
    ]))->assertRedirect();

    $monitor->refresh();
    expect($monitor->name)->toBe('Renamed');
    expect($monitor->is_active)->toBeFalse();
});

it('deletes a monitor and cascades its checks and incidents', function () {
    $monitor = Monitor::factory()->create();
    Check::factory()->for($monitor)->create();
    Incident::factory()->for($monitor)->create();

    delete("/monitors/{$monitor->id}")->assertRedirect('/monitors');

    expect(Monitor::count())->toBe(0);
    expect(Check::count())->toBe(0);
    expect(Incident::count())->toBe(0);
});
