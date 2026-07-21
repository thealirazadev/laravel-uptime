<?php

/**
 * Rollup benchmark — measures how long `uptime:rollup hour` and `uptime:rollup day`
 * take over a synthetically seeded raw `checks` dataset.
 *
 * It is self-contained: it boots the framework against a throwaway SQLite database,
 * bulk-inserts the dataset, times each rollup command, prints a result line, and
 * deletes the temporary database. It never touches the dev database.
 *
 * Usage:
 *   php benchmarks/rollup_benchmark.php [monitors] [days] [checks_per_hour]
 *
 * Example (≈500k rows): php benchmarks/rollup_benchmark.php 50 7 60
 *
 * Numbers reported in the README were measured on the hardware documented there;
 * re-run locally for your own environment.
 */

use App\Models\CheckRollup;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Loading a monitor's raw checks for grouping is memory-hungry at large sizes;
// raise the limit so the benchmark is reproducible under the default 128M.
ini_set('memory_limit', '1024M');

require __DIR__.'/../vendor/autoload.php';

$monitors = (int) ($argv[1] ?? 50);
$days = (int) ($argv[2] ?? 7);
$perHour = (int) ($argv[3] ?? 60);

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tmp = tempnam(sys_get_temp_dir(), 'uptime_bench_').'.sqlite';
touch($tmp);

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $tmp,
    'cache.default' => 'array',
    'queue.default' => 'sync',
]);
DB::purge('sqlite');

// Throwaway database: trade durability for seed speed.
DB::statement('PRAGMA journal_mode = MEMORY');
DB::statement('PRAGMA synchronous = OFF');

Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);

$now = Carbon::now()->startOfHour();

// Seed monitors.
$monitorRows = [];
for ($m = 1; $m <= $monitors; $m++) {
    $monitorRows[] = [
        'id' => $m,
        'name' => "Monitor {$m}",
        'url' => "https://example-{$m}.test",
        'interval_seconds' => 60,
        'timeout_seconds' => 10,
        'expected_status' => 200,
        'confirmation_threshold' => 2,
        'is_active' => 1,
        'status' => 'up',
        'next_check_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ];
}
DB::table('monitors')->insert($monitorRows);

// Seed raw checks across completed hours. About 2% of checks fail; failures carry
// no response time, matching how the app records unreachable targets.
$totalHours = $days * 24;
$buffer = [];
$rows = 0;
$flush = function () use (&$buffer) {
    if ($buffer !== []) {
        DB::table('checks')->insert($buffer);
        $buffer = [];
    }
};

DB::beginTransaction();
for ($m = 1; $m <= $monitors; $m++) {
    for ($h = 1; $h <= $totalHours; $h++) {
        $hourStart = $now->copy()->subHours($h);
        for ($c = 0; $c < $perHour; $c++) {
            $failed = ($rows % 50) === 0;
            $buffer[] = [
                'monitor_id' => $m,
                'ok' => $failed ? 0 : 1,
                'http_status' => $failed ? 503 : 200,
                'response_time_ms' => $failed ? null : random_int(40, 400),
                'error' => $failed ? 'status_mismatch:503' : null,
                'checked_at' => $hourStart->copy()->addSeconds(intdiv(3600, max($perHour, 1)) * $c),
            ];
            $rows++;
            if (count($buffer) >= 5000) {
                $flush();
            }
        }
    }
}
$flush();
DB::commit();

$checkCount = DB::table('checks')->count();

// Time the hourly rollup.
$t0 = hrtime(true);
Artisan::call('uptime:rollup hour');
$hourMs = (hrtime(true) - $t0) / 1e6;
$hourRollups = CheckRollup::where('period', 'hour')->count();

// Time the daily rollup (aggregates the hourly rows just written).
$t0 = hrtime(true);
Artisan::call('uptime:rollup day');
$dayMs = (hrtime(true) - $t0) / 1e6;
$dayRollups = CheckRollup::where('period', 'day')->count();

@unlink($tmp);

printf(
    "monitors=%d days=%d checks/hour=%d | raw_checks=%s | rollup hour: %.0f ms (%s rows) | rollup day: %.0f ms (%s rows) | peak_mem=%.0f MB\n",
    $monitors,
    $days,
    $perHour,
    number_format($checkCount),
    $hourMs,
    number_format($hourRollups),
    $dayMs,
    number_format($dayRollups),
    memory_get_peak_usage(true) / 1048576,
);
