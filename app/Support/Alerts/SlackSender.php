<?php

namespace App\Support\Alerts;

use App\Models\AlertChannel;
use Illuminate\Support\Facades\Http;

class SlackSender implements AlertSender
{
    public function send(AlertChannel $channel, AlertPayload $payload): void
    {
        Http::withUserAgent(config('uptime.http_user_agent'))
            ->timeout(10)
            ->post($channel->destination(), ['text' => $this->text($payload)])
            ->throw();
    }

    protected function text(AlertPayload $payload): string
    {
        $monitor = $payload->monitor;

        return match ($payload->event) {
            'incident.opened' => sprintf(
                'DOWN: %s (%s) — %s. Since %s.',
                $monitor['name'],
                $monitor['url'],
                $payload->incident['summary'] ?? 'check failed',
                $payload->startedAtHuman(),
            ),
            'incident.closed' => sprintf(
                'RECOVERED: %s (%s). Down %s.',
                $monitor['name'],
                $monitor['url'],
                $payload->durationHuman() ?? 'a moment',
            ),
            'ssl.expiry_warning' => sprintf(
                'SSL: certificate for %s expires in %d days (%s).',
                $monitor['name'],
                $payload->ssl['days_left'],
                $payload->sslExpiresAtHuman(),
            ),
            default => 'Test alert from laravel-uptime.',
        };
    }
}
