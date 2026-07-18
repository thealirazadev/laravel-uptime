<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Support\Alerts\AlertPayload;
use App\Support\Ssl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunSslCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $monitorId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('ssl:'.$this->monitorId))->dontRelease()->expireAfter(60),
        ];
    }

    public function handle(Ssl $ssl): void
    {
        $monitor = Monitor::find($this->monitorId);

        if ($monitor === null || ! $monitor->is_active || ! $monitor->isHttps()) {
            return;
        }

        $expiresAt = $ssl->expiresAt($monitor->url);

        // Connection or parse failure: keep stale values, never alert. The HTTP
        // check owns reachability, so a TLS read failure is not a downtime signal.
        if ($expiresAt === null) {
            $monitor->ssl_checked_at = now();
            $monitor->save();
            Log::warning('ssl.check_failed', ['monitor_id' => $monitor->id]);

            return;
        }

        $threshold = $monitor->applySslResult($expiresAt);

        if ($threshold === null) {
            return;
        }

        $daysLeft = Monitor::sslDaysLeft($expiresAt);

        foreach ($monitor->enabledChannels() as $channel) {
            SendAlert::dispatch($channel->id, AlertPayload::sslWarning($monitor, $expiresAt, $daysLeft, $threshold));
        }

        Log::warning('ssl.warning_sent', [
            'monitor_id' => $monitor->id,
            'threshold' => $threshold,
            'days_left' => $daysLeft,
        ]);
    }
}
