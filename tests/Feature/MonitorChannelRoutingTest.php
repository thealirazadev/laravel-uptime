<?php

use App\Models\AlertChannel;
use App\Models\Monitor;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('attaches selected channels when creating a monitor', function () {
    $a = AlertChannel::factory()->create();
    $b = AlertChannel::factory()->create();

    post('/monitors', [
        'name' => 'Routed',
        'url' => 'https://routed.example',
        'interval_seconds' => 300,
        'timeout_seconds' => 10,
        'expected_status' => 200,
        'confirmation_threshold' => 2,
        'channels' => [$a->id, $b->id],
    ])->assertRedirect();

    expect(Monitor::first()->channels()->pluck('alert_channels.id')->all())
        ->toEqualCanonicalizing([$a->id, $b->id]);
});

it('syncs channel selection on update including detaching', function () {
    $a = AlertChannel::factory()->create();
    $b = AlertChannel::factory()->create();
    $monitor = Monitor::factory()->create();
    $monitor->channels()->attach([$a->id, $b->id]);

    put("/monitors/{$monitor->id}", [
        'name' => $monitor->name,
        'url' => $monitor->url,
        'interval_seconds' => 300,
        'timeout_seconds' => 10,
        'expected_status' => 200,
        'confirmation_threshold' => 2,
        'is_active' => '1',
        'channels' => [$a->id],
    ])->assertRedirect();

    expect($monitor->channels()->pluck('alert_channels.id')->all())->toEqual([$a->id]);
});

it('rejects an unknown channel id', function () {
    post('/monitors', [
        'name' => 'Bad',
        'url' => 'https://bad.example',
        'interval_seconds' => 300,
        'timeout_seconds' => 10,
        'expected_status' => 200,
        'confirmation_threshold' => 2,
        'channels' => [9999],
    ])->assertSessionHasErrors('channels.0');

    expect(Monitor::count())->toBe(0);
});

it('shows the channel checkboxes on the monitor form', function () {
    $channel = AlertChannel::factory()->create(['name' => 'Pager webhook']);

    get('/monitors/create')->assertOk()->assertSee('Pager webhook');
});
