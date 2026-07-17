<?php

namespace App\Models;

use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** The currently open incident for this monitor, if any. */
    public function openIncident(): ?Incident
    {
        return $this->incidents()->whereNull('closed_at')->latest('started_at')->first();
    }

    public function isHttps(): bool
    {
        return str_starts_with(strtolower($this->url), 'https://');
    }
}
