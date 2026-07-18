<?php

use App\Models\AlertChannel;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('requires authentication for channel routes', function () {
    auth()->logout();

    get('/channels')->assertRedirect('/login');
});

it('shows an empty state when there are no channels', function () {
    get('/channels')->assertOk()->assertSee('No alert channels yet');
});

it('creates a webhook channel with an encrypted config', function () {
    post('/channels', [
        'type' => 'webhook',
        'name' => 'Ops webhook',
        'url' => 'https://example.com/hook',
        'secret' => 'top-secret',
    ])->assertRedirect('/channels');

    $channel = AlertChannel::sole();
    expect($channel->type)->toBe('webhook');
    expect($channel->config['url'])->toBe('https://example.com/hook');
    expect($channel->config['secret'])->toBe('top-secret');

    // Stored ciphertext must not contain the plaintext URL or secret.
    $raw = DB::table('alert_channels')->where('id', $channel->id)->value('config');
    expect($raw)->not->toContain('example.com');
    expect($raw)->not->toContain('top-secret');
});

it('rejects a non-https webhook url', function () {
    post('/channels', [
        'type' => 'webhook',
        'name' => 'Bad',
        'url' => 'http://example.com/hook',
    ])->assertSessionHasErrors('url');

    expect(AlertChannel::count())->toBe(0);
});

it('requires the type-specific field', function () {
    post('/channels', ['type' => 'mail', 'name' => 'Inbox'])
        ->assertSessionHasErrors('to');

    post('/channels', ['type' => 'slack', 'name' => 'Chan'])
        ->assertSessionHasErrors('webhook_url');
});

it('does not prefill secret fields on the edit form', function () {
    $channel = AlertChannel::factory()->webhook(true)->create([
        'config' => ['url' => 'https://secret.example/hook', 'secret' => 'abcd1234'],
    ]);

    $body = get("/channels/{$channel->id}/edit")->assertOk()->getContent();

    expect($body)->not->toContain('https://secret.example/hook');
    expect($body)->not->toContain('abcd1234');
});

it('keeps the existing secret when the field is left blank on update', function () {
    $channel = AlertChannel::factory()->webhook(true)->create([
        'config' => ['url' => 'https://keep.example/hook', 'secret' => 'keepme'],
    ]);

    put("/channels/{$channel->id}", [
        'name' => 'Renamed',
        'is_enabled' => '1',
        'url' => '',
        'secret' => '',
    ])->assertRedirect('/channels');

    $channel->refresh();
    expect($channel->name)->toBe('Renamed');
    expect($channel->config['url'])->toBe('https://keep.example/hook');
    expect($channel->config['secret'])->toBe('keepme');
});

it('updates the url when a new value is provided', function () {
    $channel = AlertChannel::factory()->webhook()->create([
        'config' => ['url' => 'https://old.example/hook'],
    ]);

    put("/channels/{$channel->id}", [
        'name' => $channel->name,
        'is_enabled' => '1',
        'url' => 'https://new.example/hook',
    ])->assertRedirect('/channels');

    expect($channel->fresh()->config['url'])->toBe('https://new.example/hook');
});

it('can disable a channel', function () {
    $channel = AlertChannel::factory()->create();

    put("/channels/{$channel->id}", [
        'name' => $channel->name,
        'url' => '',
        'is_enabled' => '0',
    ])->assertRedirect('/channels');

    expect($channel->fresh()->is_enabled)->toBeFalse();
});

it('deletes a channel', function () {
    $channel = AlertChannel::factory()->create();

    delete("/channels/{$channel->id}")->assertRedirect('/channels');

    expect(AlertChannel::count())->toBe(0);
});
