<?php

namespace App\Models;

use Database\Factories\AlertChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AlertChannel extends Model
{
    /** @use HasFactory<AlertChannelFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'config',
        'is_enabled',
    ];

    /**
     * Config keys stored for a channel type. `secret` (webhook) is optional.
     *
     * @return list<string>
     */
    public static function configKeys(string $type): array
    {
        return match ($type) {
            'mail' => ['to'],
            'slack' => ['webhook_url'],
            'webhook' => ['url', 'secret'],
            default => [],
        };
    }

    /** Config keys that are secrets and must be masked when redisplayed. */
    public static function secretKeys(string $type): array
    {
        return match ($type) {
            'slack' => ['webhook_url'],
            'webhook' => ['url', 'secret'],
            default => [],
        };
    }

    protected function casts(): array
    {
        return [
            // Encrypted at rest: Slack/webhook URLs and secrets are sensitive and
            // must never be stored or logged in plaintext.
            'config' => 'encrypted:array',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Monitor, $this>
     */
    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'alert_channel_monitor');
    }

    /** The destination address/URL for this channel type. */
    public function destination(): ?string
    {
        return match ($this->type) {
            'mail' => $this->config['to'] ?? null,
            'slack' => $this->config['webhook_url'] ?? null,
            'webhook' => $this->config['url'] ?? null,
            default => null,
        };
    }

    public function secret(): ?string
    {
        return $this->config['secret'] ?? null;
    }
}
