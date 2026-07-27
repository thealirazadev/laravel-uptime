<?php

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\MonitorGroup;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('requires authentication for maintenance routes', function () {
    auth()->logout();

    get('/maintenance-windows')->assertRedirect('/login');
});

it('shows an empty state when there are no windows', function () {
    get('/maintenance-windows')->assertOk()->assertSee('No maintenance windows yet');
});

it('creates a window and syncs its monitors and groups', function () {
    $monitor = Monitor::factory()->create();
    $group = MonitorGroup::factory()->create();

    post('/maintenance-windows', [
        'title' => 'DB upgrade',
        'starts_at' => '2026-08-01T02:00',
        'ends_at' => '2026-08-01T04:00',
        'note' => 'every first of the month',
        'monitors' => [$monitor->id],
        'groups' => [$group->id],
    ])->assertRedirect('/maintenance-windows');

    $window = MaintenanceWindow::sole();
    expect($window->title)->toBe('DB upgrade')
        ->and($window->monitors)->toHaveCount(1)
        ->and($window->groups)->toHaveCount(1);
});

it('rejects an end that is not after the start', function () {
    $monitor = Monitor::factory()->create();

    post('/maintenance-windows', [
        'title' => 'Bad range',
        'starts_at' => '2026-08-01T04:00',
        'ends_at' => '2026-08-01T02:00',
        'monitors' => [$monitor->id],
    ])->assertSessionHasErrors('ends_at');

    expect(MaintenanceWindow::count())->toBe(0);
});

it('rejects a window with no monitors and no groups', function () {
    post('/maintenance-windows', [
        'title' => 'Covers nothing',
        'starts_at' => '2026-08-01T02:00',
        'ends_at' => '2026-08-01T04:00',
    ])->assertSessionHasErrors('monitors');

    expect(MaintenanceWindow::count())->toBe(0);
});

it('updates a window and re-syncs targets', function () {
    $window = MaintenanceWindow::factory()->create();
    $a = Monitor::factory()->create();
    $b = Monitor::factory()->create();
    $window->monitors()->attach($a->id);

    put("/maintenance-windows/{$window->id}", [
        'title' => 'Renamed',
        'starts_at' => '2026-08-02T01:00',
        'ends_at' => '2026-08-02T03:00',
        'monitors' => [$b->id],
    ])->assertRedirect('/maintenance-windows');

    $window->refresh();
    expect($window->title)->toBe('Renamed')
        ->and($window->monitors->pluck('id')->all())->toBe([$b->id]);
});

it('deletes a window', function () {
    $window = MaintenanceWindow::factory()->create();

    delete("/maintenance-windows/{$window->id}")->assertRedirect('/maintenance-windows');

    expect(MaintenanceWindow::count())->toBe(0);
});
