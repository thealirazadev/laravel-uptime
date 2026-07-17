<?php

// Runtime configuration for the monitoring engine. Values come from the
// UPTIME_* environment variables documented in .env.example; defaults here
// mirror those so a missing variable never breaks a check.

return [

    // Descending list of "days before expiry" thresholds at which an SSL
    // warning fires. Expiry itself (0) is an implicit final threshold added
    // by the SSL check, so it is not listed here.
    'ssl_warn_days' => collect(explode(',', (string) env('UPTIME_SSL_WARN_DAYS', '30,14,7')))
        ->map(fn ($day) => (int) trim($day))
        ->filter(fn ($day) => $day > 0)
        ->unique()
        ->sortDesc()
        ->values()
        ->all(),

    // Retention windows (days). Rollups always run before pruning so nothing
    // is deleted before it has been aggregated.
    'raw_retention_days' => (int) env('UPTIME_RAW_RETENTION_DAYS', 7),
    'hourly_retention_days' => (int) env('UPTIME_HOURLY_RETENTION_DAYS', 90),
    'daily_retention_days' => (int) env('UPTIME_DAILY_RETENTION_DAYS', 365),

    // User agent sent on outbound HTTP checks and webhook deliveries.
    'http_user_agent' => (string) env('UPTIME_HTTP_USER_AGENT', 'laravel-uptime/1.0'),

];
