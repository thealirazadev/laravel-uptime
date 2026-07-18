<?php

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

it('renders the custom 404 page for an unknown url', function () {
    get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('Page not found');
});

it('returns a server_error envelope when a status json request fails', function () {
    // A multi-segment path matches the status/*/json render rule but not the
    // single-segment status route, so it exercises the 500 branch.
    Route::get('/status/deep/x/json', fn () => throw new RuntimeException('boom'));

    getJson('/status/deep/x/json')
        ->assertStatus(500)
        ->assertExactJson(['error' => ['code' => 'server_error', 'message' => 'Something went wrong.']]);
});
