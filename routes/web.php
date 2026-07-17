<?php

use App\Http\Controllers\AlertChannelController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\MonitorGroupController;
use App\Http\Controllers\StatusPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Public, unauthenticated status page for a monitor group (HTML + JSON twin).
Route::get('/status/{slug}', [StatusPageController::class, 'show'])->name('status.show');
Route::get('/status/{slug}/json', [StatusPageController::class, 'json'])->name('status.json');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('monitors', MonitorController::class);

    Route::resource('channels', AlertChannelController::class)->except(['show']);
    Route::post('/channels/{channel}/test', [AlertChannelController::class, 'test'])->name('channels.test');

    Route::resource('groups', MonitorGroupController::class)->except(['show'])
        ->parameters(['groups' => 'group']);

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
});
