<?php

use App\Models\AlertChannel;
use App\Models\Incident;
use App\Models\Monitor;
use App\Support\Alerts\AlertPayload;
use App\Support\Alerts\MailSender;
use App\Support\Alerts\SlackSender;
use App\Support\Alerts\WebhookSender;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

it('sends a slack down message with name, url, and summary', function () {
    Http::fake();
    Carbon::setTestNow('2026-07-18 09:41:00');

    $channel = AlertChannel::factory()->create([
        'type' => 'slack',
        'config' => ['webhook_url' => 'https://hooks.slack.com/services/x'],
    ]);
    $monitor = Monitor::factory()->create(['name' => 'Client A API', 'url' => 'https://api.client-a.example']);
    $incident = Incident::factory()->for($monitor)->create(['summary' => 'status_mismatch:503']);

    (new SlackSender)->send($channel, AlertPayload::incidentOpened($monitor, $incident));

    Http::assertSent(function ($request) {
        expect($request->url())->toBe('https://hooks.slack.com/services/x');
        expect($request['text'])->toContain('DOWN: Client A API (https://api.client-a.example)');
        expect($request['text'])->toContain('status_mismatch:503');

        return true;
    });

    Carbon::setTestNow();
});

it('posts a webhook body matching the contract', function () {
    Http::fake();

    $channel = AlertChannel::factory()->create([
        'type' => 'webhook',
        'config' => ['url' => 'https://hook.example/x'],
    ]);
    $monitor = Monitor::factory()->create(['name' => 'Client A API', 'url' => 'https://api.client-a.example']);
    $incident = Incident::factory()->for($monitor)->create(['summary' => 'status_mismatch:503']);

    (new WebhookSender)->send($channel, AlertPayload::incidentOpened($monitor, $incident));

    Http::assertSent(function ($request) use ($monitor, $incident) {
        $data = $request->data();

        expect($data['event'])->toBe('incident.opened');
        expect($data['monitor'])->toBe([
            'id' => $monitor->id,
            'name' => 'Client A API',
            'url' => 'https://api.client-a.example',
            'status' => 'down',
        ]);
        expect($data['incident']['id'])->toBe($incident->id);
        expect($data['incident']['closed_at'])->toBeNull();
        expect($request->header('Content-Type')[0])->toContain('application/json');

        return true;
    });
});

it('sends a mail alert with the contract subject and recipient', function () {
    $channel = AlertChannel::factory()->create([
        'type' => 'mail',
        'config' => ['to' => 'ops@example.test'],
    ]);
    $monitor = Monitor::factory()->create(['name' => 'Client A']);
    $incident = Incident::factory()->for($monitor)->create();

    (new MailSender)->send($channel, AlertPayload::incidentOpened($monitor, $incident));

    $messages = app('mailer')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1);

    $email = $messages->first()->getOriginalMessage();
    expect($email->getSubject())->toBe('[laravel-uptime] DOWN: Client A');
    expect($email->getTo()[0]->getAddress())->toBe('ops@example.test');
});

it('throws when the endpoint responds with a non-2xx status', function () {
    Http::fake(['*' => Http::response('', 500)]);

    $channel = AlertChannel::factory()->create([
        'type' => 'webhook',
        'config' => ['url' => 'https://hook.example/x'],
    ]);

    expect(fn () => (new WebhookSender)->send($channel, AlertPayload::test()))
        ->toThrow(RequestException::class);
});
