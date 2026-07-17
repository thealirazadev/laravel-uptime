<?php

namespace Database\Factories;

use App\Models\MonitorGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MonitorGroup>
 */
class MonitorGroupFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'is_public' => true,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }
}
