<?php

namespace Database\Factories;

use App\Models\AlertChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertChannel>
 */
class AlertChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'webhook',
            'name' => fake()->unique()->words(2, true),
            'config' => ['url' => 'https://hooks.example/'.fake()->uuid()],
            'is_enabled' => true,
        ];
    }

    public function mail(): static
    {
        return $this->state(fn () => [
            'type' => 'mail',
            'config' => ['to' => fake()->unique()->safeEmail()],
        ]);
    }

    public function slack(): static
    {
        return $this->state(fn () => [
            'type' => 'slack',
            'config' => ['webhook_url' => 'https://hooks.slack.com/services/'.fake()->uuid()],
        ]);
    }

    public function webhook(bool $signed = false): static
    {
        return $this->state(fn () => [
            'type' => 'webhook',
            'config' => array_filter([
                'url' => 'https://hooks.example/'.fake()->uuid(),
                'secret' => $signed ? fake()->sha256() : null,
            ]),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
