<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lab>
 */
class LabFactory extends Factory
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
            'name' => 'Laboratorio ' . $this->faker->numberBetween(1, 20),
            'area' => $this->faker->randomElement(['Pretratamiento', 'Tratamiento Secundario', 'Sedimentacion', 'Analitica']),
            'process_line' => $this->faker->randomElement(['Oxigenacion', 'Nitrificacion', 'Neutralizacion', 'Clarificacion']),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
