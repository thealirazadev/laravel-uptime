<?php

namespace App\Support\Alerts;

use App\Models\Incident;
use App\Models\Monitor;
use Carbon\CarbonInterface;

/**
 * Immutable, primitive-only snapshot of what an alert conveys. Built at dispatch
 * time from models so the queued job carries plain data (no serialized Eloquent
 * models) and every sender reads the same facts.
 */
final class AlertPayload
{
    /**
     * @param  array{id:int,name:string,url:string,status:string}|null  $monitor
     * @param  array{id:int,started_at:string,closed_at:?string,duration_seconds:?int,summary:?string}|null  $incident
     * @param  array{expires_at:string,days_left:int,threshold_days:int}|null  $ssl
     */
    public function __construct(
        public readonly string $event,
        public readonly ?array $monitor = null,
        public readonly ?array $incident = null,
        public readonly ?array $ssl = null,
    ) {}

    public static function incidentOpened(Monitor $monitor, Incident $incident): self
    {
        return new self('incident.opened', self::monitorData($monitor, 'down'), self::incidentData($incident));
    }

    public static function incidentClosed(Monitor $monitor, Incident $incident): self
    {
        return new self('incident.closed', self::monitorData($monitor, 'up'), self::incidentData($incident));
    }

    public static function sslWarning(Monitor $monitor, CarbonInterface $expiresAt, int $daysLeft, int $thresholdDays): self
    {
        return new self('ssl.expiry_warning', self::monitorData($monitor, $monitor->status), null, [
            'expires_at' => $expiresAt->toIso8601ZuluString(),
            'days_left' => $daysLeft,
            'threshold_days' => $thresholdDays,
        ]);
    }

    public static function test(): self
    {
        return new self('test');
    }

    /**
     * @return array{id:int,name:string,url:string,status:string}
     */
    private static function monitorData(Monitor $monitor, string $status): array
    {
        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'url' => $monitor->url,
            'status' => $status,
        ];
    }

    /**
     * @return array{id:int,started_at:string,closed_at:?string,duration_seconds:?int,summary:?string}
     */
    private static function incidentData(Incident $incident): array
    {
        $closedAt = $incident->closed_at;

        return [
            'id' => $incident->id,
            'started_at' => $incident->started_at->toIso8601ZuluString(),
            'closed_at' => $closedAt?->toIso8601ZuluString(),
            'duration_seconds' => $closedAt ? $incident->started_at->diffInSeconds($closedAt) : null,
            'summary' => $incident->summary,
        ];
    }
}
