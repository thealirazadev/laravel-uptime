<?php

use App\Jobs\SendAlert;
use App\Models\AlertChannel;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    actingAs(User::factory()->create());
    Queue::fake();
});

it('queues a test alert for an enabled channel', function () {
    $channel = AlertChannel::factory()->create();

    post("/channels/{$channel->id}/test")
        ->assertRedirect()
        ->assertSessionHas('status');

    Queue::assertPushed(SendAlert::class, fn ($job) => $job->channelId === $channel->id
        && $job->payload->event === 'test'
        && $job->incidentId === null);
});

it('does not queue a test for a disabled channel', function () {
    $channel = AlertChannel::factory()->disabled()->create();

    post("/channels/{$channel->id}/test")
        ->assertRedirect()
        ->assertSessionHas('error');

    Queue::assertNothingPushed();
});
