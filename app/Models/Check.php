<?php

namespace App\Models;

use Database\Factories\CheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Check extends Model
{
    /** @use HasFactory<CheckFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'monitor_id',
        'ok',
        'http_status',
        'response_time_ms',
        'error',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'http_status' => 'integer',
            'response_time_ms' => 'integer',
            'checked_at' => 'datetime',
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
