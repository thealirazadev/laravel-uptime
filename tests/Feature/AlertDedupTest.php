<?php

use App\Jobs\SendAlert;
use App\Models\AlertChannel;
use App\Models\Monitor;
use App\Support\CheckOutcome;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function outcomeFail(): CheckOutcome
{
    return CheckOutcome::failure('connection_failed');
}

function outcomeOk(): CheckOutcome
{
    return CheckOutcome::success(200, 120);
}

function monitorWithChannels(): array
{
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);
    $a = AlertChannel::factory()->create(['name' => 'A']);
    $b = AlertChannel::factory()->create(['name' => 'B']);
    $disabled = AlertChannel::factory()->disabled()->create(['name' => 'C']);
    $monitor->channels()->attach([$a->id, $b->id, $disabled->id]);

    return [$monitor, $a, $b, $disabled];
}

it('dispatches exactly one open alert per enabled channel', function () {
    [$monitor, $a, $b, $disabled] = monitorWithChannels();

    $monitor->applyCheckResult(outcomeFail());
    Queue::assertNothingPushed();

    $monitor->applyCheckResult(outcomeFail());

    Queue::assertPushed(SendAlert::class, 2);
    Queue::assertPushed(SendAlert::class, fn ($job) => $job->channelId === $a->id && $job->payload->event === 'incident.opened');
    Queue::assertPushed(SendAlert::class, fn ($job) => $job->channelId === $b->id && $job->payload->event === 'incident.opened');
    Queue::assertNotPushed(SendAlert::class, fn ($job) => $job->channelId === $disabled->id);
});

it('does not re-alert on continued failures against an open incident', function () {
    [$monitor] = monitorWithChannels();

    $monitor->applyCheckResult(outcomeFail());
    $monitor->applyCheckResult(outcomeFail());

    foreach (range(1, 50) as $i) {
        $monitor->applyCheckResult(outcomeFail());
    }

    Queue::assertPushed(SendAlert::class, 2);
});

it('dispatches exactly one recovery alert per channel on close', function () {
    [$monitor, $a, $b] = monitorWithChannels();

    $monitor->applyCheckResult(outcomeFail());
    $monitor->applyCheckResult(outcomeFail());
    $monitor->applyCheckResult(outcomeOk());
    $monitor->applyCheckResult(outcomeOk());

    Queue::assertPushed(SendAlert::class, 4);
    Queue::assertPushed(SendAlert::class, fn ($job) => $job->channelId === $a->id && $job->payload->event === 'incident.closed');
    Queue::assertPushed(SendAlert::class, fn ($job) => $job->channelId === $b->id && $job->payload->event === 'incident.closed');
});

it('dispatches nothing for a flapping sequence below the threshold', function () {
    [$monitor] = monitorWithChannels();

    foreach ([outcomeFail(), outcomeOk(), outcomeFail(), outcomeOk()] as $outcome) {
        $monitor->applyCheckResult($outcome);
    }

    Queue::assertNothingPushed();
});

it('does not alert a detached channel', function () {
    $monitor = Monitor::factory()->create(['confirmation_threshold' => 2, 'status' => 'up']);
    $attached = AlertChannel::factory()->create();
    AlertChannel::factory()->create(); // exists but not attached
    $monitor->channels()->attach($attached->id);

    $monitor->applyCheckResult(outcomeFail());
    $monitor->applyCheckResult(outcomeFail());

    Queue::assertPushed(SendAlert::class, 1);
    Queue::assertPushed(SendAlert::class, fn ($job) => $job->channelId === $attached->id);
});
