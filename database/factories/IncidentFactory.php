<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'started_at' => now(),
            'closed_at' => null,
            'summary' => 'connection_failed',
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => now(),
            'started_at' => $attributes['started_at'] ?? now()->subMinutes(10),
        ]);
    }
}
