<?php

use App\Models\Monitor;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-18 00:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function expiresInDays(int $days): Carbon
{
    return Carbon::now()->addDays($days)->addHours(1);
}

it('warns at the 30-day threshold for a cert expiring in 20 days', function () {
    $monitor = Monitor::factory()->create();

    expect($monitor->applySslResult(expiresInDays(20)))->toBe(30);
    expect($monitor->ssl_notified_days)->toBe(30);
});

it('does not warn again for the same threshold', function () {
    $monitor = Monitor::factory()->create();

    expect($monitor->applySslResult(expiresInDays(20)))->toBe(30);
    expect($monitor->applySslResult(expiresInDays(18)))->toBeNull();
    expect($monitor->applySslResult(expiresInDays(16)))->toBeNull();
});

it('warns again when crossing into a tighter threshold', function () {
    $monitor = Monitor::factory()->create();

    expect($monitor->applySslResult(expiresInDays(20)))->toBe(30);
    expect($monitor->applySslResult(expiresInDays(10)))->toBe(14);
    expect($monitor->applySslResult(expiresInDays(5)))->toBe(7);
});

it('warns once at expiry via the implicit zero threshold', function () {
    $monitor = Monitor::factory()->create(['ssl_notified_days' => 7]);
    // Pretend the 7-day warning already fired for this cert.
    $monitor->ssl_expires_at = Carbon::now()->addDays(5);
    $monitor->save();

    expect($monitor->applySslResult(expiresInDays(0)))->toBe(0);
    expect($monitor->applySslResult(expiresInDays(-1)) ?? 'null')->toBe('null');
});

it('stays silent when the cert is well outside every threshold', function () {
    $monitor = Monitor::factory()->create();

    expect($monitor->applySslResult(expiresInDays(60)))->toBeNull();
    expect($monitor->ssl_expires_at)->not->toBeNull();
});

it('re-arms all thresholds after a renewal', function () {
    $monitor = Monitor::factory()->create();

    expect($monitor->applySslResult(expiresInDays(5)))->toBe(7);

    // Certificate renewed: expiry jumps far into the future, then approaches again.
    expect($monitor->applySslResult(expiresInDays(90)))->toBeNull();
    expect($monitor->ssl_notified_days)->toBeNull();
    expect($monitor->applySslResult(expiresInDays(20)))->toBe(30);
});
