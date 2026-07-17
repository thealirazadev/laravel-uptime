<?php

namespace App\Models;

use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'started_at',
        'closed_at',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /**
     * @return HasMany<IncidentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(IncidentEvent::class);
    }

    /** Append a timeline entry for this incident. */
    public function recordEvent(string $type, string $message): IncidentEvent
    {
        return $this->events()->create([
            'type' => $type,
            'message' => $message,
        ]);
    }
}
