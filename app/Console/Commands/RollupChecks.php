<?php

namespace App\Console\Commands;

use App\Models\Check;
use App\Models\CheckRollup;
use App\Models\Monitor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RollupChecks extends Command
{
    protected $signature = 'uptime:rollup {period : hour or day}';

    protected $description = 'Aggregate checks into hourly or daily rollups (idempotent upsert).';

    public function handle(): int
    {
        $period = $this->argument('period');

        if (! in_array($period, ['hour', 'day'], true)) {
            $this->error('Period must be "hour" or "day".');

            return self::FAILURE;
        }

        $period === 'hour' ? $this->rollupHours() : $this->rollupDays();

        Log::info('rollup.completed', ['period' => $period]);

        return self::SUCCESS;
    }

    /** Aggregate raw checks from completed hours into hourly rollups. */
    protected function rollupHours(): void
    {
        $boundary = now()->startOfHour();

        Monitor::query()->pluck('id')->each(function (int $monitorId) use ($boundary) {
            $checks = Check::query()
                ->where('monitor_id', $monitorId)
                ->where('checked_at', '<', $boundary)
                ->get(['ok', 'response_time_ms', 'checked_at']);

            if ($checks->isEmpty()) {
                return;
            }

            $rows = $checks
                ->groupBy(fn (Check $check) => $check->checked_at->format('Y-m-d H:00:00'))
                ->map(function (Collection $group, string $bucket) use ($monitorId) {
                    $responsive = $group->whereNotNull('response_time_ms');

                    return $this->row($monitorId, 'hour', $bucket,
                        $group->count(),
                        $group->where('ok', false)->count(),
                        $responsive->isEmpty() ? null : (int) round($responsive->avg('response_time_ms')),
                        $responsive->isEmpty() ? null : (int) $responsive->min('response_time_ms'),
                        $responsive->isEmpty() ? null : (int) $responsive->max('response_time_ms'),
                    );
                })
                ->values()
                ->all();

            $this->upsert($rows);
        });
    }

    /** Aggregate completed hourly rollups into daily rollups. */
    protected function rollupDays(): void
    {
        $boundary = now()->startOfDay();

        Monitor::query()->pluck('id')->each(function (int $monitorId) use ($boundary) {
            $hours = CheckRollup::query()
                ->where('monitor_id', $monitorId)
                ->where('period', 'hour')
                ->where('period_start', '<', $boundary)
                ->get();

            if ($hours->isEmpty()) {
                return;
            }

            $rows = $hours
                ->groupBy(fn (CheckRollup $rollup) => $rollup->period_start->format('Y-m-d 00:00:00'))
                ->map(function (Collection $group, string $bucket) use ($monitorId) {
                    $withAvg = $group->whereNotNull('avg_response_time_ms');
                    $weight = $withAvg->sum('checks_total');

                    return $this->row($monitorId, 'day', $bucket,
                        (int) $group->sum('checks_total'),
                        (int) $group->sum('checks_failed'),
                        // Weight each hour's average by its check count.
                        $weight > 0 ? (int) round($withAvg->sum(fn (CheckRollup $r) => $r->avg_response_time_ms * $r->checks_total) / $weight) : null,
                        $group->whereNotNull('min_response_time_ms')->min('min_response_time_ms'),
                        $group->whereNotNull('max_response_time_ms')->max('max_response_time_ms'),
                    );
                })
                ->values()
                ->all();

            $this->upsert($rows);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(
        int $monitorId,
        string $period,
        string $bucket,
        int $total,
        int $failed,
        ?int $avg,
        ?int $min,
        ?int $max,
    ): array {
        return [
            'monitor_id' => $monitorId,
            'period' => $period,
            'period_start' => Carbon::parse($bucket)->toDateTimeString(),
            'checks_total' => $total,
            'checks_failed' => $failed,
            'avg_response_time_ms' => $avg,
            'min_response_time_ms' => $min,
            'max_response_time_ms' => $max,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function upsert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        CheckRollup::upsert(
            $rows,
            ['monitor_id', 'period', 'period_start'],
            ['checks_total', 'checks_failed', 'avg_response_time_ms', 'min_response_time_ms', 'max_response_time_ms', 'updated_at'],
        );
    }
}
