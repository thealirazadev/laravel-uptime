<?php

namespace App\Models;

use Database\Factories\IncidentEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentEvent extends Model
{
    /** @use HasFactory<IncidentEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'incident_id',
        'type',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
