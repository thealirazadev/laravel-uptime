<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('redirects guests from the dashboard to login', function () {
    get('/dashboard')->assertRedirect('/login');
});

it('logs an operator in with valid credentials', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $response = post('/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ]);

    $response->assertRedirect('/dashboard');
    expect(auth()->check())->toBeTrue();
});

it('rejects wrong credentials with a generic message and no enumeration', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $response = post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe('These credentials do not match our records.');
    expect(auth()->check())->toBeFalse();
});

it('validates login input', function () {
    post('/login', ['email' => 'not-an-email', 'password' => ''])
        ->assertSessionHasErrors(['email', 'password']);
});

it('logs an operator out', function () {
    $user = User::factory()->create();

    actingAs($user)->post('/logout')->assertRedirect('/login');
    expect(auth()->check())->toBeFalse();
});

it('creates an operator via the uptime:user command', function () {
    artisan('uptime:user', [
        '--name' => 'Ops',
        '--email' => 'ops@example.test',
        '--password' => 'password123',
    ])->assertExitCode(0);

    $user = User::where('email', 'ops@example.test')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

it('rejects a duplicate email in the uptime:user command', function () {
    User::factory()->create(['email' => 'dupe@example.test']);

    artisan('uptime:user', [
        '--name' => 'Ops',
        '--email' => 'dupe@example.test',
        '--password' => 'password123',
    ])->assertExitCode(1);
});
