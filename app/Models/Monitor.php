<?php

namespace App\Models;

use App\Jobs\SendAlert;
use App\Support\Alerts\AlertPayload;
use App\Support\CheckOutcome;
use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use HasFactory;

    /** Allowed check intervals in seconds; the only values the form accepts. */
    public const INTERVALS = [60, 300, 900, 1800, 3600];

    /**
     * Interval values mapped to human labels for the monitor form.
     *
     * @return array<int, string>
     */
    public static function intervalOptions(): array
    {
        return [
            60 => 'Every minute',
            300 => 'Every 5 minutes',
            900 => 'Every 15 minutes',
            1800 => 'Every 30 minutes',
            3600 => 'Every 60 minutes',
        ];
    }

    protected $fillable = [
        'monitor_group_id',
        'name',
        'url',
        'interval_seconds',
        'timeout_seconds',
        'expected_status',
        'expected_keyword',
        'confirmation_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interval_seconds' => 'integer',
            'timeout_seconds' => 'integer',
            'expected_status' => 'integer',
            'confirmation_threshold' => 'integer',
            'is_active' => 'boolean',
            'consecutive_failures' => 'integer',
            'consecutive_successes' => 'integer',
            'ssl_notified_days' => 'integer',
            'first_failed_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'ssl_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MonitorGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MonitorGroup::class, 'monitor_group_id');
    }

    /**
     * @return HasMany<Check, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * @return BelongsToMany<AlertChannel, $this>
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(AlertChannel::class, 'alert_channel_monitor');
    }

    /**
     * Enabled channels attached to this monitor: the exact set an alert fans out to.
     *
     * @return Collection<int, AlertChannel>
     */
    public function enabledChannels(): Collection
    {
        return $this->channels()->where('is_enabled', true)->get();
    }

    /** The currently open incident for this monitor, if any. */
    public function openIncident(): ?Incident
    {
        return $this->incidents()->whereNull('closed_at')->latest('started_at')->first();
    }

    public function isHttps(): bool
    {
        return str_starts_with(strtolower($this->url), 'https://');
    }

    /**
     * The confirmation-threshold state machine. Called once per completed check,
     * inside the per-monitor overlap lock, so counters never interleave.
     *
     * Counters are mutually resetting; status flips only when a streak reaches the
     * confirmation threshold. up|unknown -> down and (down|unknown) -> up are the
     * only transitions, and unknown -> up is silent (no incident, no alert).
     */
    public function applyCheckResult(CheckOutcome $outcome): void
    {
        $this->last_checked_at = now();

        if ($outcome->ok) {
            $this->consecutive_successes++;
            $this->consecutive_failures = 0;
            $this->first_failed_at = null;
            $this->last_error = null;

            if ($this->status !== 'up' && $this->consecutive_successes >= $this->confirmation_threshold) {
                $wasDown = $this->status === 'down';
                $this->status = 'up';

                // down -> up closes the incident; unknown -> up is silent.
                if ($wasDown) {
                    $this->save();
                    $this->closeIncident();

                    return;
                }
            }
        } else {
            $this->consecutive_failures++;
            $this->consecutive_successes = 0;
            $this->last_error = $outcome->error;

            if ($this->consecutive_failures === 1) {
                // Remember when the failing streak began; this becomes the
                // incident's started_at, not the moment it was confirmed.
                $this->first_failed_at = now();
            }

            if ($this->status !== 'down' && $this->consecutive_failures >= $this->confirmation_threshold) {
                $this->status = 'down';
                $this->save();
                $this->openIncidentRecord();

                return;
            }
        }

        $this->save();
    }

    /** Open the single incident for the current down transition and log it. */
    protected function openIncidentRecord(): void
    {
        Log::warning('monitor.down', ['monitor_id' => $this->id]);

        $incident = $this->incidents()->create([
            'started_at' => $this->first_failed_at ?? now(),
            'summary' => $this->last_error,
        ]);
        $incident->recordEvent('opened', 'Incident opened: '.($this->last_error ?? 'check failed').'.');

        Log::warning('incident.opened', [
            'monitor_id' => $this->id,
            'incident_id' => $incident->id,
        ]);

        // The only place open alerts fire: dedup is structural, not a flag.
        foreach ($this->enabledChannels() as $channel) {
            SendAlert::dispatch($channel->id, AlertPayload::incidentOpened($this, $incident), $incident->id);
        }
    }

    /** Close the open incident on recovery and log it. */
    protected function closeIncident(): void
    {
        Log::info('monitor.up', ['monitor_id' => $this->id]);

        $incident = $this->openIncident();

        if ($incident === null) {
            return;
        }

        $incident->closed_at = now();
        $incident->save();
        $incident->recordEvent('closed', 'Monitor recovered.');

        Log::info('incident.closed', [
            'monitor_id' => $this->id,
            'incident_id' => $incident->id,
        ]);

        // The only place recovery alerts fire.
        foreach ($this->enabledChannels() as $channel) {
            SendAlert::dispatch($channel->id, AlertPayload::incidentClosed($this, $incident), $incident->id);
        }
    }
}
