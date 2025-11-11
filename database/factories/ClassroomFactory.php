<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $this->faker->unique(true);

        return [
            'name' => 'Aula ' . $this->faker->numberBetween(1, 20),
            'building' => $this->faker->randomElement(['A', 'B', 'C', 'D']),
            'floor' => $this->faker->numberBetween(1, 3),
            'capacity' => $this->faker->numberBetween(20, 50),
        ];
    }
}
