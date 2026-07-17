<?php

use App\Models\MonitorGroup;
use App\Models\User;

use function Pest\Laravel\post;

it('throttles login after five attempts per minute', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(302);
    }

    post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
});

it('sets rate-limit headers on the status page', function () {
    MonitorGroup::factory()->create(['slug' => 'client-a']);

    $response = $this->get('/status/client-a');

    expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();
    expect($response->headers->get('X-RateLimit-Limit'))->toBe('60');
});

it('throttles the status page after sixty requests per minute', function () {
    MonitorGroup::factory()->create(['slug' => 'client-a']);

    foreach (range(1, 60) as $i) {
        $this->get('/status/client-a')->assertOk();
    }

    $this->get('/status/client-a')->assertStatus(429);
});

it('returns the rate-limited envelope on the json endpoint', function () {
    MonitorGroup::factory()->create(['slug' => 'client-a']);

    foreach (range(1, 60) as $i) {
        $this->getJson('/status/client-a/json')->assertOk();
    }

    $this->getJson('/status/client-a/json')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');
});
