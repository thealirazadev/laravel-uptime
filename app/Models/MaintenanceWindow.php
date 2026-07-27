<?php

namespace App\Models;

use Database\Factories\MaintenanceWindowFactory;
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
}
