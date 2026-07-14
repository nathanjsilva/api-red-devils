<?php

namespace Database\Factories;

use App\Models\Pelada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define o estado padrão do modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pelada_id' => Pelada::factory(),
            'name' => 'Time '.fake()->unique()->numberBetween(1, 1000),
        ];
    }
}
