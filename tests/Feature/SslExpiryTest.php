<?php

use App\Jobs\RunSslCheck;
use App\Jobs\SendAlert;
use App\Models\AlertChannel;
use App\Models\Monitor;
use App\Support\Ssl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

function fakeSsl(?CarbonImmutable $expiresAt): void
{
    app()->instance(Ssl::class, new class($expiresAt) extends Ssl
    {
        public function __construct(private ?CarbonImmutable $date) {}

        public function expiresAt(string $url): ?CarbonImmutable
        {
            return $this->date;
        }
    });
}

beforeEach(function () {
    Carbon::setTestNow('2026-07-18 00:00:00');
    Queue::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('stores expiry and warns the monitor channels at the crossed threshold', function () {
    $monitor = Monitor::factory()->create(['url' => 'https://client-a.example']);
    $channel = AlertChannel::factory()->create();
    $monitor->channels()->attach($channel->id);

    $expiresAt = CarbonImmutable::now()->addDays(20);
    fakeSsl($expiresAt);

    (new RunSslCheck($monitor->id))->handle(app(Ssl::class));

    $monitor->refresh();
    expect($monitor->ssl_expires_at)->not->toBeNull();
    expect($monitor->ssl_notified_days)->toBe(30);
    Queue::assertPushed(SendAlert::class, fn ($job) => $job->channelId === $channel->id
        && $job->payload->event === 'ssl.expiry_warning'
        && $job->payload->ssl['threshold_days'] === 30
        && $job->incidentId === null);
});

it('does not warn again on a rerun for the same threshold', function () {
    $monitor = Monitor::factory()->create(['url' => 'https://client-a.example']);
    $monitor->channels()->attach(AlertChannel::factory()->create()->id);

    fakeSsl(CarbonImmutable::now()->addDays(20));
    (new RunSslCheck($monitor->id))->handle(app(Ssl::class));

    fakeSsl(CarbonImmutable::now()->addDays(18));
    (new RunSslCheck($monitor->id))->handle(app(Ssl::class));

    Queue::assertPushed(SendAlert::class, 1);
});

it('logs a check failure and does not alert when the certificate cannot be read', function () {
    $monitor = Monitor::factory()->create(['url' => 'https://client-a.example']);
    $monitor->channels()->attach(AlertChannel::factory()->create()->id);

    fakeSsl(null);
    (new RunSslCheck($monitor->id))->handle(app(Ssl::class));

    $monitor->refresh();
    expect($monitor->ssl_checked_at)->not->toBeNull();
    expect($monitor->ssl_expires_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('skips non-https monitors', function () {
    $monitor = Monitor::factory()->create(['url' => 'http://plain.example']);
    fakeSsl(CarbonImmutable::now()->addDays(5));

    (new RunSslCheck($monitor->id))->handle(app(Ssl::class));

    expect($monitor->fresh()->ssl_checked_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('dispatches ssl checks only for active https monitors', function () {
    Monitor::factory()->create(['url' => 'https://a.example']);
    Monitor::factory()->create(['url' => 'http://b.example']);
    Monitor::factory()->paused()->create(['url' => 'https://c.example']);

    artisan('uptime:dispatch-ssl')->assertExitCode(0);

    Queue::assertPushed(RunSslCheck::class, 1);
});
