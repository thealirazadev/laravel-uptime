<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MaintenanceWindowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MaintenanceWindow extends Model
{
    /** @use HasFactory<MaintenanceWindowFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'starts_at',
        'ends_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Monitor, $this>
     */
    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'maintenance_window_monitor');
    }

    /**
     * @return BelongsToMany<MonitorGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(MonitorGroup::class, 'maintenance_window_monitor_group');
    }

    /**
     * Windows whose bounds contain the given instant (default now). Bounds are
     * inclusive so a window that begins exactly now already suppresses.
     *
     * @param  Builder<MaintenanceWindow>  $query
     * @return Builder<MaintenanceWindow>
     */
    public function scopeActive(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where('starts_at', '<=', $at)->where('ends_at', '>=', $at);
    }

    public function isActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->starts_at->lessThanOrEqualTo($at) && $this->ends_at->greaterThanOrEqualTo($at);
    }
}
