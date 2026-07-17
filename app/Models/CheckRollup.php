<?php

namespace App\Models;

use Database\Factories\CheckRollupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckRollup extends Model
{
    /** @use HasFactory<CheckRollupFactory> */
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'period',
        'period_start',
        'checks_total',
        'checks_failed',
        'avg_response_time_ms',
        'min_response_time_ms',
        'max_response_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'checks_total' => 'integer',
            'checks_failed' => 'integer',
            'avg_response_time_ms' => 'integer',
            'min_response_time_ms' => 'integer',
            'max_response_time_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
