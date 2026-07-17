<?php

namespace Database\Factories;

use App\Models\Check;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Check>
 */
class CheckFactory extends Factory
{
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'ok' => true,
            'http_status' => 200,
            'response_time_ms' => fake()->numberBetween(40, 600),
            'error' => null,
            'checked_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'ok' => false,
            'http_status' => null,
            'response_time_ms' => null,
            'error' => 'connection_failed',
        ]);
    }
}
