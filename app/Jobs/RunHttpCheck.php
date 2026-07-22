<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Support\CheckOutcome;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunHttpCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A failing target is a valid result, not a job error; never retry the check. */
    public int $tries = 1;

    public function __construct(public int $monitorId) {}

    /**
     * Overlap lock keyed by monitor id: two workers never check the same monitor
     * at once, and a duplicate job is dropped rather than requeued. The lock lives
     * at least as long as a check can possibly run (see lockSeconds), so a slow
     * redirect chain can never outlast the lock and let a second concurrent check
     * of the same monitor begin.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->monitorId))
                ->dontRelease()
                ->expireAfter(static::lockSeconds()),
        ];
    }

    /**
     * Worst-case runtime of a single check: the initial request plus every
     * followed redirect, each capped at the per-request timeout ceiling, with a
     * fixed buffer for the body scan and the check/incident writes. The overlap
     * lock is sized from this so it cannot expire while a check is still in flight.
     */
    public static function lockSeconds(): int
    {
        return (1 + Monitor::MAX_REDIRECTS) * Monitor::MAX_TIMEOUT_SECONDS + 15;
    }

    public function handle(): void
    {
        $monitor = Monitor::find($this->monitorId);

        if ($monitor === null || ! $monitor->is_active) {
            return;
        }

        $outcome = $this->performCheck($monitor);

        $monitor->checks()->create([
            'ok' => $outcome->ok,
            'http_status' => $outcome->httpStatus,
            'response_time_ms' => $outcome->responseTimeMs,
            'error' => $outcome->error,
            'checked_at' => now(),
        ]);

        if (! $outcome->ok) {
            Log::warning('check.failed', [
                'monitor_id' => $monitor->id,
                'reason' => $outcome->error,
                'http_status' => $outcome->httpStatus,
            ]);
        }

        $monitor->applyCheckResult($outcome);
    }

    /**
     * Run the request and classify the result. Every throwable from the HTTP call
     * is caught and turned into a failed check: the check can fail, the job cannot.
     */
    protected function performCheck(Monitor $monitor): CheckOutcome
    {
        $start = hrtime(true);

        try {
            $response = Http::withUserAgent(config('uptime.http_user_agent'))
                ->timeout($monitor->timeout_seconds)
                ->withOptions(['allow_redirects' => ['max' => Monitor::MAX_REDIRECTS]])
                ->get($monitor->url);
        } catch (ConnectionException $e) {
            return CheckOutcome::failure($this->classifyConnectionError($e));
        } catch (\Throwable $e) {
            Log::error('check.exception', [
                'monitor_id' => $monitor->id,
                'exception' => $e::class,
            ]);

            return CheckOutcome::failure('connection_failed');
        }

        $elapsedMs = (int) round((hrtime(true) - $start) / 1_000_000);
        $status = $response->status();

        if ($status !== $monitor->expected_status) {
            return CheckOutcome::failure('status_mismatch:'.$status, $status, $elapsedMs);
        }

        if ($monitor->expected_keyword !== null) {
            // Scan only the first 256 KB so a huge body cannot exhaust memory.
            $body = mb_substr($response->body(), 0, 262_144);

            if (stripos($body, $monitor->expected_keyword) === false) {
                return CheckOutcome::failure('keyword_missing', $status, $elapsedMs);
            }
        }

        return CheckOutcome::success($status, $elapsedMs);
    }

    protected function classifyConnectionError(ConnectionException $e): string
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, 'timed out'),
            str_contains($message, 'timeout'),
            str_contains($message, 'error 28') => 'timeout',
            str_contains($message, 'ssl'),
            str_contains($message, 'certificate'),
            str_contains($message, 'error 35'),
            str_contains($message, 'error 60') => 'tls_error',
            default => 'connection_failed',
        };
    }
}
