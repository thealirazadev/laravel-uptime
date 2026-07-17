<?php

namespace App\Support\Alerts;

use App\Models\AlertChannel;
use Illuminate\Support\Facades\Mail;

class MailSender implements AlertSender
{
    public function send(AlertChannel $channel, AlertPayload $payload): void
    {
        $to = $channel->destination();

        Mail::send('emails.alert', ['payload' => $payload], function ($message) use ($to, $payload) {
            $message->to($to)->subject($this->subject($payload));
        });
    }

    protected function subject(AlertPayload $payload): string
    {
        $name = $payload->monitorName();

        $detail = match ($payload->event) {
            'incident.opened' => "DOWN: {$name}",
            'incident.closed' => "RECOVERED: {$name}",
            'ssl.expiry_warning' => "SSL expiry: {$name} ({$payload->ssl['days_left']} days)",
            default => 'Test alert',
        };

        return '['.config('app.name').'] '.$detail;
    }
}
