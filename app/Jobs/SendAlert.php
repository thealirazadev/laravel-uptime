<?php

namespace App\Jobs;

use App\Models\AlertChannel;
use App\Models\Incident;
use App\Support\Alerts\AlertPayload;
use App\Support\Alerts\AlertSender;
use App\Support\Alerts\MailSender;
use App\Support\Alerts\SlackSender;
use App\Support\Alerts\WebhookSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(
        public int $channelId,
        public AlertPayload $payload,
        public ?int $incidentId = null,
    ) {}

    public function handle(): void
    {
        $channel = AlertChannel::find($this->channelId);

        // A channel detached or disabled after dispatch is silently skipped.
        if ($channel === null || ! $channel->is_enabled) {
            return;
        }

        $this->senderFor($channel->type)->send($channel, $this->payload);

        $this->recordEvent('alert_sent', 'Alert sent via '.ucfirst($channel->type)." ({$channel->name}).");
        Log::info('alert.sent', [
            'channel_id' => $channel->id,
            'event' => $this->payload->event,
        ]);
    }

    /** Runs once, after the final retry fails. One channel's failure never touches another. */
    public function failed(?Throwable $exception): void
    {
        $channel = AlertChannel::find($this->channelId);
        $label = $channel ? ucfirst($channel->type)." ({$channel->name})" : "channel #{$this->channelId}";

        $this->recordEvent('alert_failed', "Alert failed via {$label}.");

        // Log the class and HTTP status only: never the webhook URL or secret.
        Log::warning('alert.send_failed', [
            'channel_id' => $this->channelId,
            'event' => $this->payload->event,
            'exception' => $exception ? $exception::class : null,
            'status' => $exception instanceof RequestException ? $exception->response->status() : null,
        ]);
    }

    protected function senderFor(string $type): AlertSender
    {
        return match ($type) {
            'mail' => new MailSender,
            'slack' => new SlackSender,
            default => new WebhookSender,
        };
    }

    protected function recordEvent(string $type, string $message): void
    {
        if ($this->incidentId === null) {
            return;
        }

        Incident::find($this->incidentId)?->recordEvent($type, $message);
    }
}
