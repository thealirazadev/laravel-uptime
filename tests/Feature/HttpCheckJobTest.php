<?php

use App\Jobs\RunHttpCheck;
use App\Models\Monitor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function runCheck(Monitor $monitor): void
{
    (new RunHttpCheck($monitor->id))->handle();
}

it('records an ok check when the status matches', function () {
    Http::fake(['*' => Http::response('OK', 200)]);
    $monitor = Monitor::factory()->create(['expected_status' => 200]);

    runCheck($monitor);

    $check = $monitor->checks()->sole();
    expect($check->ok)->toBeTrue();
    expect($check->http_status)->toBe(200);
    expect($check->error)->toBeNull();
    expect($monitor->fresh()->consecutive_successes)->toBe(1);
});

it('records a status mismatch failure', function () {
    Http::fake(['*' => Http::response('', 503)]);
    $monitor = Monitor::factory()->create(['expected_status' => 200]);

    runCheck($monitor);

    $check = $monitor->checks()->sole();
    expect($check->ok)->toBeFalse();
    expect($check->error)->toBe('status_mismatch:503');
    expect($check->http_status)->toBe(503);
});

it('passes when the expected keyword is present (case-insensitive)', function () {
    Http::fake(['*' => Http::response('<h1>WELCOME back</h1>', 200)]);
    $monitor = Monitor::factory()->create(['expected_keyword' => 'Welcome']);

    runCheck($monitor);

    expect($monitor->checks()->sole()->ok)->toBeTrue();
});

it('fails when the expected keyword is missing', function () {
    Http::fake(['*' => Http::response('<h1>Nope</h1>', 200)]);
    $monitor = Monitor::factory()->create(['expected_keyword' => 'Welcome']);

    runCheck($monitor);

    $check = $monitor->checks()->sole();
    expect($check->ok)->toBeFalse();
    expect($check->error)->toBe('keyword_missing');
});

it('classifies a timeout without throwing from the job', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
    $monitor = Monitor::factory()->create();

    runCheck($monitor);

    $check = $monitor->checks()->sole();
    expect($check->ok)->toBeFalse();
    expect($check->error)->toBe('timeout');
    expect($check->response_time_ms)->toBeNull();
});

it('classifies a generic connection failure', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));
    $monitor = Monitor::factory()->create();

    runCheck($monitor);

    expect($monitor->checks()->sole()->error)->toBe('connection_failed');
});

it('follows a redirect and evaluates the final response', function () {
    Http::fake([
        'redirect.example/*' => Http::response('', 301, ['Location' => 'https://final.example/']),
        'final.example/*' => Http::response('OK', 200),
    ]);
    $monitor = Monitor::factory()->create([
        'url' => 'https://redirect.example/',
        'expected_status' => 200,
    ]);

    runCheck($monitor);

    $check = $monitor->checks()->sole();
    expect($check->ok)->toBeTrue();
    expect($check->http_status)->toBe(200);
});

it('turns an over-long redirect chain into a failed check, not a job error', function () {
    // Every hop 301s onward, so the redirect cap is exceeded; the resulting
    // Guzzle exception must be caught and recorded as a failed check.
    Http::fake(['*' => Http::response('', 301, ['Location' => 'https://loop.example/next'])]);
    $monitor = Monitor::factory()->create([
        'url' => 'https://loop.example/',
        'expected_status' => 200,
    ]);

    runCheck($monitor);

    $check = $monitor->checks()->sole();
    expect($check->ok)->toBeFalse();
    expect($check->error)->toBe('connection_failed');
});

it('writes exactly one check row per run', function () {
    Http::fake(['*' => Http::response('OK', 200)]);
    $monitor = Monitor::factory()->create();

    runCheck($monitor);

    expect($monitor->checks()->count())->toBe(1);
});

it('does nothing for a paused monitor', function () {
    Http::fake(['*' => Http::response('OK', 200)]);
    $monitor = Monitor::factory()->paused()->create();

    runCheck($monitor);

    expect($monitor->checks()->count())->toBe(0);
    Http::assertNothingSent();
});

it('holds the overlap lock at least as long as a check can run', function () {
    $overlap = collect((new RunHttpCheck(1))->middleware())
        ->first(fn ($middleware) => $middleware instanceof WithoutOverlapping);

    // A check follows the initial request plus every redirect, each capped at the
    // per-request timeout ceiling. The lock must outlast that whole worst case so a
    // slow redirect chain can never let a second concurrent check of the same
    // monitor begin.
    $worstCaseRuntime = (1 + Monitor::MAX_REDIRECTS) * Monitor::MAX_TIMEOUT_SECONDS;

    expect($overlap)->not->toBeNull();
    expect($overlap->expiresAfter)->toBeGreaterThanOrEqual($worstCaseRuntime);
});

it('drops a duplicate job that cannot acquire the overlap lock', function () {
    Http::fake(['*' => Http::response('OK', 200)]);
    $monitor = Monitor::factory()->create();

    $lock = Cache::lock('laravel-queue-overlap:'.RunHttpCheck::class.':'.$monitor->id, 60);
    expect($lock->get())->toBeTrue();

    // Dispatch goes through the sync queue, which applies the job middleware.
    RunHttpCheck::dispatch($monitor->id);

    expect($monitor->checks()->count())->toBe(0);

    $lock->release();
});
