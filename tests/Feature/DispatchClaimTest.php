<?php

use App\Jobs\RunHttpCheck;
use App\Models\Monitor;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Queue::fake();
});

it('dispatches a check for each due monitor', function () {
    Monitor::factory()->due()->count(3)->create();

    artisan('uptime:dispatch-checks')->assertExitCode(0);

    Queue::assertPushed(RunHttpCheck::class, 3);
});

it('advances next_check_at by the interval when claiming', function () {
    $monitor = Monitor::factory()->due()->create(['interval_seconds' => 300]);

    artisan('uptime:dispatch-checks');

    $monitor->refresh();
    expect($monitor->next_check_at->isFuture())->toBeTrue();
    expect($monitor->next_check_at->gt(now()->addSeconds(200)))->toBeTrue();
});

it('does not re-dispatch a claimed monitor on a second immediate tick', function () {
    Monitor::factory()->due()->create();

    artisan('uptime:dispatch-checks');
    artisan('uptime:dispatch-checks');

    // The claim advanced next_check_at past now, so the second tick sees nothing.
    Queue::assertPushed(RunHttpCheck::class, 1);
});

it('skips paused monitors', function () {
    Monitor::factory()->due()->paused()->create();

    artisan('uptime:dispatch-checks');

    Queue::assertNotPushed(RunHttpCheck::class);
});

it('skips monitors that are not yet due', function () {
    Monitor::factory()->create(['next_check_at' => now()->addMinutes(5)]);

    artisan('uptime:dispatch-checks');

    Queue::assertNotPushed(RunHttpCheck::class);
});

it('claims a row only once even across two passes on the same due set', function () {
    $monitor = Monitor::factory()->due()->create();

    // Simulate two racing ticks by capturing the same boundary the command uses.
    $now = now();
    $first = Monitor::query()->whereKey($monitor->id)->where('next_check_at', '<=', $now)
        ->update(['next_check_at' => $now->copy()->addSeconds($monitor->interval_seconds)]);
    $second = Monitor::query()->whereKey($monitor->id)->where('next_check_at', '<=', $now)
        ->update(['next_check_at' => $now->copy()->addSeconds($monitor->interval_seconds)]);

    expect($first)->toBe(1);
    expect($second)->toBe(0);
});
