<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentEvent>
 */
class IncidentEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'type' => 'opened',
            'message' => 'Incident opened.',
            'created_at' => now(),
        ];
    }
}
