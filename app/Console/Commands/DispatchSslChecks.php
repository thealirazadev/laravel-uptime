<?php

namespace App\Console\Commands;

use App\Jobs\RunSslCheck;
use App\Models\Monitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchSslChecks extends Command
{
    protected $signature = 'uptime:dispatch-ssl';

    protected $description = 'Dispatch daily SSL expiry checks for active https monitors.';

    public function handle(): int
    {
        $dispatched = 0;

        Monitor::query()
            ->where('is_active', true)
            ->get(['id', 'url'])
            ->filter(fn (Monitor $monitor) => $monitor->isHttps())
            ->each(function (Monitor $monitor) use (&$dispatched) {
                RunSslCheck::dispatch($monitor->id);
                $dispatched++;
            });

        Log::info('ssl.dispatch_completed', ['dispatched' => $dispatched]);

        return self::SUCCESS;
    }
}
