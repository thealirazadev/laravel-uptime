<?php

namespace App\Support\Alerts;

use App\Models\AlertChannel;

interface AlertSender
{
    /**
     * Deliver the payload to the channel. Must throw on any delivery failure so
     * the SendAlert job can retry and, on final failure, record alert_failed.
     */
    public function send(AlertChannel $channel, AlertPayload $payload): void;
}
