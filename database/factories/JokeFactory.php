<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Joke;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Joke>
 */
class JokeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->numberBetween(1, 100_000),
            'type' => fake()->randomElement(['general', 'programming', 'knock-knock']),
            'setup' => fake()->sentence(),
            'punchline' => fake()->sentence(),
        ];
    }
}
