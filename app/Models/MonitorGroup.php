<?php

namespace App\Models;

use Database\Factories\MonitorGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitorGroup extends Model
{
    /** @use HasFactory<MonitorGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Monitor, $this>
     */
    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }
}
