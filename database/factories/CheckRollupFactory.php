<?php

namespace Database\Factories;

use App\Models\CheckRollup;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckRollup>
 */
class CheckRollupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'period' => 'hour',
            'period_start' => now()->startOfHour(),
            'checks_total' => 12,
            'checks_failed' => 0,
            'avg_response_time_ms' => 180,
            'min_response_time_ms' => 120,
            'max_response_time_ms' => 240,
        ];
    }

    public function day(): static
    {
        return $this->state(fn () => [
            'period' => 'day',
            'period_start' => now()->startOfDay(),
            'checks_total' => 288,
        ]);
    }
}
