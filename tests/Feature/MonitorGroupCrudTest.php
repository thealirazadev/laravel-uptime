<?php

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

it('requires authentication for group routes', function () {
    auth()->logout();

    get('/groups')->assertRedirect('/login');
});

it('shows an empty state when there are no groups', function () {
    get('/groups')->assertOk()->assertSee('No groups yet');
});

it('creates a group with an explicit slug', function () {
    post('/groups', ['name' => 'Client A', 'slug' => 'client-a', 'is_public' => '1'])
        ->assertRedirect('/groups');

    $group = MonitorGroup::sole();
    expect($group->slug)->toBe('client-a');
    expect($group->is_public)->toBeTrue();
});

it('derives a slug from the name when blank', function () {
    post('/groups', ['name' => 'Client B Sites', 'slug' => '', 'is_public' => '1'])
        ->assertRedirect('/groups');

    expect(MonitorGroup::sole()->slug)->toBe('client-b-sites');
});

it('rejects a duplicate slug', function () {
    MonitorGroup::factory()->create(['slug' => 'taken']);

    post('/groups', ['name' => 'Other', 'slug' => 'taken', 'is_public' => '1'])
        ->assertSessionHasErrors('slug');

    expect(MonitorGroup::count())->toBe(1);
});

it('normalizes a messy slug into kebab-case', function () {
    post('/groups', ['name' => 'Bad', 'slug' => 'Not Kebab!', 'is_public' => '1'])
        ->assertRedirect('/groups');

    expect(MonitorGroup::sole()->slug)->toBe('not-kebab');
});

it('rejects a slug that cannot be slugified', function () {
    post('/groups', ['name' => '####', 'slug' => '####', 'is_public' => '1'])
        ->assertSessionHasErrors('slug');

    expect(MonitorGroup::count())->toBe(0);
});

it('allows keeping the same slug on update', function () {
    $group = MonitorGroup::factory()->create(['slug' => 'client-a']);

    put("/groups/{$group->id}", ['name' => 'Renamed', 'slug' => 'client-a', 'is_public' => '0'])
        ->assertRedirect('/groups');

    $group->refresh();
    expect($group->name)->toBe('Renamed');
    expect($group->is_public)->toBeFalse();
});

it('detaches monitors when a group is deleted', function () {
    $group = MonitorGroup::factory()->create();
    $monitor = Monitor::factory()->create(['monitor_group_id' => $group->id]);

    delete("/groups/{$group->id}")->assertRedirect('/groups');

    expect(MonitorGroup::count())->toBe(0);
    expect($monitor->fresh()->monitor_group_id)->toBeNull();
});

it('assigns a group when creating a monitor', function () {
    $group = MonitorGroup::factory()->create();

    post('/monitors', [
        'name' => 'Grouped',
        'monitor_group_id' => $group->id,
        'url' => 'https://grouped.example',
        'interval_seconds' => 300,
        'timeout_seconds' => 10,
        'expected_status' => 200,
        'confirmation_threshold' => 2,
    ])->assertRedirect();

    expect(Monitor::sole()->monitor_group_id)->toBe($group->id);
});
