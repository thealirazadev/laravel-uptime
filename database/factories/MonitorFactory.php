<?php

namespace Database\Factories;

use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'monitor_group_id' => null,
            'name' => fake()->unique()->words(2, true),
            'url' => 'https://'.fake()->unique()->domainName(),
            'interval_seconds' => 300,
            'timeout_seconds' => 10,
            'expected_status' => 200,
            'expected_keyword' => null,
            'confirmation_threshold' => 2,
            'is_active' => true,
            'status' => 'unknown',
            'consecutive_failures' => 0,
            'consecutive_successes' => 0,
            'first_failed_at' => null,
            'last_checked_at' => null,
            'next_check_at' => now(),
            'last_error' => null,
        ];
    }

    /** Monitor whose next check is already due. */
    public function due(): static
    {
        return $this->state(fn () => ['next_check_at' => now()->subMinute()]);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function up(): static
    {
        return $this->state(fn () => [
            'status' => 'up',
            'consecutive_successes' => 1,
            'last_checked_at' => now(),
        ]);
    }

    public function down(): static
    {
        return $this->state(fn () => [
            'status' => 'down',
            'consecutive_failures' => 2,
            'first_failed_at' => now()->subMinutes(2),
            'last_checked_at' => now(),
            'last_error' => 'connection_failed',
        ]);
    }
}
