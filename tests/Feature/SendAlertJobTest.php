<?php

use App\Jobs\SendAlert;
use App\Models\AlertChannel;
use App\Models\Incident;
use App\Models\Monitor;
use App\Support\Alerts\AlertPayload;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('delivers via the webhook sender and records an alert_sent event', function () {
    Http::fake();

    $channel = AlertChannel::factory()->create([
        'type' => 'webhook',
        'name' => 'Ops hook',
        'config' => ['url' => 'https://hook.example/x'],
    ]);
    $monitor = Monitor::factory()->create();
    $incident = Incident::factory()->for($monitor)->create();

    (new SendAlert($channel->id, AlertPayload::incidentOpened($monitor, $incident), $incident->id))->handle();

    Http::assertSentCount(1);
    expect($incident->events()->where('type', 'alert_sent')->count())->toBe(1);
    expect($incident->events()->where('message', 'Alert sent via Webhook (Ops hook).')->exists())->toBeTrue();
});

it('skips a disabled channel without sending', function () {
    Http::fake();

    $channel = AlertChannel::factory()->disabled()->create([
        'type' => 'webhook',
        'config' => ['url' => 'https://hook.example/x'],
    ]);
    $monitor = Monitor::factory()->create();
    $incident = Incident::factory()->for($monitor)->create();

    (new SendAlert($channel->id, AlertPayload::incidentOpened($monitor, $incident), $incident->id))->handle();

    Http::assertNothingSent();
    expect($incident->events()->count())->toBe(0);
});

it('throws so the queue retries when delivery fails', function () {
    Http::fake(['*' => Http::response('', 500)]);

    $channel = AlertChannel::factory()->create([
        'type' => 'webhook',
        'config' => ['url' => 'https://hook.example/x'],
    ]);
    $monitor = Monitor::factory()->create();
    $incident = Incident::factory()->for($monitor)->create();

    expect(fn () => (new SendAlert($channel->id, AlertPayload::incidentOpened($monitor, $incident), $incident->id))->handle())
        ->toThrow(RequestException::class);
});

it('records alert_failed on the final failure', function () {
    $channel = AlertChannel::factory()->create(['type' => 'webhook', 'name' => 'Ops hook']);
    $monitor = Monitor::factory()->create();
    $incident = Incident::factory()->for($monitor)->create();

    (new SendAlert($channel->id, AlertPayload::incidentOpened($monitor, $incident), $incident->id))
        ->failed(new RuntimeException('boom'));

    expect($incident->events()->where('type', 'alert_failed')->count())->toBe(1);
    expect($incident->events()->where('message', 'Alert failed via Webhook (Ops hook).')->exists())->toBeTrue();
});

it('has three tries with escalating backoff', function () {
    $job = new SendAlert(1, AlertPayload::test());

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([60, 300]);
});
