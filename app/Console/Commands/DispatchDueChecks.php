<?php

namespace App\Console\Commands;

use App\Jobs\RunHttpCheck;
use App\Models\Monitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchDueChecks extends Command
{
    protected $signature = 'uptime:dispatch-checks';

    protected $description = 'Claim due monitors and dispatch their HTTP checks.';

    public function handle(): int
    {
        // Capture one reference time; every claim compares against it so two
        // racing ticks read the same "due" boundary.
        $now = now();

        $due = Monitor::query()
            ->where('is_active', true)
            ->where('next_check_at', '<=', $now)
            ->orderBy('next_check_at')
            ->get(['id', 'interval_seconds']);

        $dispatched = 0;

        foreach ($due as $monitor) {
            // Atomic claim: the conditional UPDATE only advances next_check_at
            // while it is still <= now. The first tick wins (1 row affected) and
            // dispatches; a racing tick matches 0 rows and no-ops. No locks,
            // no cross-row transaction.
            $claimed = Monitor::query()
                ->whereKey($monitor->id)
                ->where('next_check_at', '<=', $now)
                ->update([
                    'next_check_at' => $now->copy()->addSeconds($monitor->interval_seconds),
                    'updated_at' => $now,
                ]);

            if ($claimed === 1) {
                RunHttpCheck::dispatch($monitor->id);
                $dispatched++;
            }
        }

        Log::info('dispatch.completed', [
            'due' => $due->count(),
            'dispatched' => $dispatched,
        ]);

        return self::SUCCESS;
    }
}
