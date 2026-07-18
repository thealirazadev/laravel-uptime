<?php

namespace App\Support\Alerts;

use App\Models\AlertChannel;
use Illuminate\Support\Facades\Http;

class WebhookSender implements AlertSender
{
    public function send(AlertChannel $channel, AlertPayload $payload): void
    {
        // Serialize once: the signature must be over the exact bytes we POST.
        $body = json_encode($this->body($payload), JSON_UNESCAPED_SLASHES);

        $headers = ['X-Uptime-Event' => $payload->event];

        if ($secret = $channel->secret()) {
            $headers['X-Uptime-Signature'] = 'sha256='.hash_hmac('sha256', $body, $secret);
        }

        Http::withUserAgent(config('uptime.http_user_agent'))
            ->timeout(10)
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post($channel->destination())
            ->throw();
    }

    /**
     * @return array<string, mixed>
     */
    protected function body(AlertPayload $payload): array
    {
        $body = [
            'event' => $payload->event,
            'sent_at' => now()->toIso8601ZuluString(),
        ];

        if ($payload->event === 'test') {
            $body['message'] = 'Test alert from laravel-uptime.';

            return $body;
        }

        $body['monitor'] = $payload->monitor;

        if ($payload->incident !== null) {
            $body['incident'] = $payload->incident;
        }

        if ($payload->ssl !== null) {
            $body['ssl'] = $payload->ssl;
        }

        return $body;
    }
}
