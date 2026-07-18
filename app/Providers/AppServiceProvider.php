<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Login: tight per-IP limit against brute force.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Public status surfaces (HTML + JSON) share one 60/min per-IP bucket. The
        // JSON twin returns the error envelope; the HTML page returns the 429 view.
        RateLimiter::for('status', fn (Request $request) => Limit::perMinute(60)
            ->by($request->ip())
            ->response(function (Request $request, array $headers) {
                if ($request->routeIs('status.json')) {
                    return response()->json(
                        ['error' => ['code' => 'rate_limited', 'message' => 'Too many requests.']],
                        429,
                        $headers,
                    );
                }

                return response()->view('errors.429', [], 429, $headers);
            }));
    }
}
