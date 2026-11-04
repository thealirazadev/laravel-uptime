<?php

namespace App\Console\Commands;

use App\Models\Check;
use App\Models\CheckRollup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneChecks extends Command
{
    protected $signature = 'uptime:prune';

    protected $description = 'Delete raw checks and stale rollups past their retention windows.';

    public function handle(): int
    {
        // Rollups run before this in the daily sequence, so pruned raw rows have
        // already been aggregated.
        //
        // Cutoffs are aligned to the bucket the next rollup level recomputes from:
        // the hourly rollup rebuilds every hour it still has raw checks for, and the
        // daily rollup rebuilds every day it still has hourly rows for. Cutting
        // mid-bucket would leave a partial hour (or day) behind that the next run
        // re-aggregates and overwrites the complete rollup with.
        $rawDeleted = Check::query()
            ->where('checked_at', '<', now()->subDays(config('uptime.raw_retention_days'))->startOfHour())
            ->delete();

        $hourlyDeleted = CheckRollup::query()
            ->where('period', 'hour')
            ->where('period_start', '<', now()->subDays(config('uptime.hourly_retention_days'))->startOfDay())
            ->delete();

        $dailyDeleted = CheckRollup::query()
            ->where('period', 'day')
            ->where('period_start', '<', now()->subDays(config('uptime.daily_retention_days')))
            ->delete();

        Log::info('prune.completed', [
            'raw' => $rawDeleted,
            'hourly' => $hourlyDeleted,
            'daily' => $dailyDeleted,
        ]);

        return self::SUCCESS;
    }
}
