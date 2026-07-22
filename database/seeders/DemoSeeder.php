<?php

namespace Database\Seeders;

use App\Models\AlertChannel;
use App\Models\Check;
use App\Models\CheckRollup;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Obviously synthetic demo data (example.com hosts, dummy channel config) that fills
 * the dashboard, the monitor detail charts, and the public status page. Used to take
 * the README screenshots; safe to re-run on a throwaway database.
 *
 * php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo Operator', 'password' => bcrypt('password')],
        );

        $public = MonitorGroup::query()->create([
            'name' => 'Acme Cloud', 'slug' => 'acme-cloud', 'is_public' => true,
        ]);
        $internal = MonitorGroup::query()->create([
            'name' => 'Internal Tools', 'slug' => 'internal-tools', 'is_public' => false,
        ]);

        $mail = AlertChannel::query()->create([
            'type' => 'mail', 'name' => 'Ops on-call inbox',
            'config' => ['to' => 'ops@example.com'], 'is_enabled' => true,
        ]);
        $slack = AlertChannel::query()->create([
            'type' => 'slack', 'name' => 'Slack #ops-alerts',
            'config' => ['webhook_url' => 'https://hooks.example.com/services/T00DEMO/B00DEMO/not-a-real-token'],
            'is_enabled' => true,
        ]);
        AlertChannel::query()->create([
            'type' => 'webhook', 'name' => 'On-call relay webhook',
            'config' => ['url' => 'https://relay.example.com/hooks/uptime', 'secret' => 'demo-secret'],
            'is_enabled' => false,
        ]);

        $marketing = $this->monitor($public, 'Marketing Site', 'https://www.example.com', 'up', 268);
        $this->history($marketing, 210, [], [3 => 4, 11 => 2]);
        $this->checks($marketing, 210, 0);

        $api = $this->monitor($public, 'Storefront API', 'https://api.example.com', 'up', 6, 7);
        $this->history($api, 320, [19 => 5, 18 => 12, 17 => 3], [1 => 41, 6 => 9]);
        $this->checks($api, 320, 0);

        $checkout = $this->monitor($public, 'Checkout Service', 'https://checkout.example.com', 'down', 91);
        $this->history($checkout, 480, [0 => 7, 1 => 12], [2 => 15]);
        $this->checks($checkout, 480, 7, 503, 'unexpected_status_503');

        $docs = $this->monitor($public, 'Docs Portal', 'https://docs.example.com', 'up', 121);
        $this->history($docs, 145, [], [22 => 3]);
        $this->checks($docs, 145, 0);

        $billing = $this->monitor($internal, 'Billing Exporter', 'https://billing.example.com', 'up', 44);
        $this->history($billing, 640, [8 => 2], [14 => 6]);
        $this->checks($billing, 640, 0);

        $legacy = $this->monitor($internal, 'Legacy Reports (paused)', 'http://legacy.example.com', 'unknown', null);
        $legacy->is_active = false;
        $legacy->save();

        foreach ([$marketing, $api, $checkout, $docs, $billing] as $monitor) {
            $monitor->channels()->sync([$mail->id, $slack->id]);
        }

        // Open incident on the down monitor, plus two resolved ones for the timeline.
        $open = $this->incident($checkout, now()->subMinutes(38), null, 'unexpected_status_503');
        $this->event($open, 'opened', 'Incident opened: unexpected_status_503.', now()->subMinutes(38));
        $this->event($open, 'alert_sent', 'Alert sent via Ops on-call inbox.', now()->subMinutes(38)->addSeconds(4));
        $this->event($open, 'alert_sent', 'Alert sent via Slack #ops-alerts.', now()->subMinutes(38)->addSeconds(6));

        $past = $this->incident($api, now()->subHours(19), now()->subHours(18)->subMinutes(5), 'connection_timeout');
        $this->event($past, 'opened', 'Incident opened: connection_timeout.', now()->subHours(19));
        $this->event($past, 'alert_sent', 'Alert sent via Slack #ops-alerts.', now()->subHours(19)->addSeconds(3));
        $this->event($past, 'alert_failed', 'Alert delivery failed via Ops on-call inbox.', now()->subHours(19)->addSeconds(9));
        $this->event($past, 'closed', 'Monitor recovered.', now()->subHours(18)->subMinutes(5));

        $older = $this->incident($marketing, now()->subDays(3)->subHours(2), now()->subDays(3)->subHours(1), 'connection_failed');
        $this->event($older, 'opened', 'Incident opened: connection_failed.', now()->subDays(3)->subHours(2));
        $this->event($older, 'closed', 'Monitor recovered.', now()->subDays(3)->subHours(1));
    }

    protected function monitor(MonitorGroup $group, string $name, string $url, string $status, ?int $sslDays, ?int $notified = null): Monitor
    {
        return Monitor::factory()->create([
            'monitor_group_id' => $group->id,
            'name' => $name,
            'url' => $url,
            'interval_seconds' => 300,
            'status' => $status,
            'consecutive_failures' => $status === 'down' ? 2 : 0,
            'consecutive_successes' => $status === 'up' ? 2 : 0,
            'first_failed_at' => $status === 'down' ? now()->subMinutes(38) : null,
            'last_checked_at' => now()->subMinutes(2),
            'next_check_at' => now()->addMinutes(3),
            'last_error' => $status === 'down' ? 'unexpected_status_503' : null,
            'ssl_expires_at' => $sslDays === null ? null : now()->addDays($sslDays),
            'ssl_checked_at' => $sslDays === null ? null : now()->subHours(4),
            'ssl_notified_days' => $notified,
        ]);
    }

    /**
     * 24 hourly and 30 daily rollups with a deterministic response-time wave, so the
     * charts and uptime percentages render the same way on every run.
     *
     * @param  array<int, int>  $failedHours  hours ago => failed checks in that hour
     * @param  array<int, int>  $failedDays  days ago => failed checks that day
     */
    protected function history(Monitor $monitor, int $baseMs, array $failedHours = [], array $failedDays = []): void
    {
        $rows = [];

        for ($i = 23; $i >= 0; $i--) {
            $avg = (int) round($baseMs * (1 + 0.22 * sin($i / 2.4)) + ($i % 5) * 7);
            $rows[] = $this->rollup($monitor, 'hour', now()->startOfHour()->subHours($i), 12, $failedHours[$i] ?? 0, $avg);
        }

        for ($i = 29; $i >= 0; $i--) {
            $avg = (int) round($baseMs * (1 + 0.18 * sin($i / 4.1)) + ($i % 7) * 5);
            $rows[] = $this->rollup($monitor, 'day', now()->startOfDay()->subDays($i), 288, $failedDays[$i] ?? 0, $avg);
        }

        CheckRollup::query()->insert($rows);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rollup(Monitor $monitor, string $period, Carbon $start, int $total, int $failed, int $avg): array
    {
        return [
            'monitor_id' => $monitor->id,
            'period' => $period,
            'period_start' => $start,
            'checks_total' => $total,
            'checks_failed' => $failed,
            'avg_response_time_ms' => $avg,
            'min_response_time_ms' => (int) round($avg * 0.72),
            'max_response_time_ms' => (int) round($avg * 1.9),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** The last 20 raw checks at the monitor's interval; the newest $failing ones fail. */
    protected function checks(Monitor $monitor, int $baseMs, int $failing, int $status = 200, ?string $error = null): void
    {
        $rows = [];

        for ($i = 0; $i < 20; $i++) {
            $failed = $i < $failing;
            $rows[] = [
                'monitor_id' => $monitor->id,
                'ok' => ! $failed,
                'http_status' => $failed ? $status : 200,
                'response_time_ms' => $failed ? null : (int) round($baseMs * (1 + 0.18 * sin($i / 1.7)) + $i % 4 * 9),
                'error' => $failed ? $error : null,
                'checked_at' => now()->subMinutes(2 + $i * 5),
            ];
        }

        Check::query()->insert($rows);
    }

    protected function incident(Monitor $monitor, Carbon $startedAt, ?Carbon $closedAt, string $summary): Incident
    {
        return $monitor->incidents()->create([
            'started_at' => $startedAt,
            'closed_at' => $closedAt,
            'summary' => $summary,
        ]);
    }

    protected function event(Incident $incident, string $type, string $message, Carbon $at): void
    {
        $event = $incident->recordEvent($type, $message);
        $event->created_at = $at;
        $event->save();
    }
}
