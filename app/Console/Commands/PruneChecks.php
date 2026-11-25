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
        // Each cutoff is aligned to the bucket of the rollup built from that
        // table: raw checks go in whole hours and hourly rollups in whole days.
        // The rollups re-aggregate every bucket they still find source rows for,
        // so a cutoff in the middle of a bucket would leave it partly deleted and
        // the next run would overwrite a complete rollup with a partial one.
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
