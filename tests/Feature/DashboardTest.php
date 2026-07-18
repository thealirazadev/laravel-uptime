<?php

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('shows a guidance empty state with no monitors', function () {
    get('/dashboard')
        ->assertOk()
        ->assertSee('No monitors yet');
});

it('lists monitors with their status on the dashboard', function () {
    Monitor::factory()->up()->create(['name' => 'Alpha site']);
    Monitor::factory()->down()->create(['name' => 'Beta site']);

    get('/dashboard')
        ->assertOk()
        ->assertSee('Alpha site')
        ->assertSee('Beta site')
        ->assertSee('Down');
});

it('lists open incidents on the dashboard', function () {
    $monitor = Monitor::factory()->down()->create(['name' => 'Gamma site']);
    Incident::factory()->for($monitor)->create(['summary' => 'connection_failed']);

    get('/dashboard')
        ->assertOk()
        ->assertSee('Open incidents')
        ->assertSee('Gamma site');
});

it('renders the incident index open first', function () {
    $monitor = Monitor::factory()->create(['name' => 'Delta site']);
    Incident::factory()->for($monitor)->resolved()->create(['summary' => 'row-resolved']);
    Incident::factory()->for($monitor)->create(['summary' => 'row-open']);

    $body = get('/incidents')->assertOk()->getContent();

    expect(strpos($body, 'row-open'))->toBeLessThan(strpos($body, 'row-resolved'));
});

it('shows an empty state on the incident index', function () {
    get('/incidents')
        ->assertOk()
        ->assertSee('No incidents recorded');
});

it('renders an incident timeline', function () {
    $monitor = Monitor::factory()->create(['name' => 'Epsilon site']);
    $incident = Incident::factory()->for($monitor)->create();
    $incident->recordEvent('opened', 'Incident opened: connection_failed.');
    $incident->recordEvent('closed', 'Monitor recovered.');

    get("/incidents/{$incident->id}")
        ->assertOk()
        ->assertSee('Epsilon site')
        ->assertSee('Incident opened')
        ->assertSee('Monitor recovered');
});
