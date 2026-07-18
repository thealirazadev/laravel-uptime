<?php

use App\Models\AlertChannel;
use App\Models\Incident;
use App\Models\Monitor;
use App\Support\Alerts\AlertPayload;
use App\Support\Alerts\WebhookSender;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

it('signs the raw body with hmac-sha256 when a secret is set', function () {
    Http::fake();
    Carbon::setTestNow('2026-07-18 09:43:05');

    $channel = AlertChannel::factory()->create([
        'type' => 'webhook',
        'config' => ['url' => 'https://hook.example/x', 'secret' => 's3cr3t'],
    ]);
    $monitor = Monitor::factory()->create();
    $incident = Incident::factory()->for($monitor)->create();

    (new WebhookSender)->send($channel, AlertPayload::incidentOpened($monitor, $incident));

    Http::assertSent(function ($request) {
        $signature = $request->header('X-Uptime-Signature')[0] ?? null;

        expect($signature)->toBe('sha256='.hash_hmac('sha256', $request->body(), 's3cr3t'));
        expect($request->header('X-Uptime-Event')[0])->toBe('incident.opened');

        return true;
    });

    Carbon::setTestNow();
});

it('omits the signature header when no secret is configured', function () {
    Http::fake();

    $channel = AlertChannel::factory()->create([
        'type' => 'webhook',
        'config' => ['url' => 'https://hook.example/x'],
    ]);

    (new WebhookSender)->send($channel, AlertPayload::test());

    Http::assertSent(fn ($request) => empty($request->header('X-Uptime-Signature')));
});
