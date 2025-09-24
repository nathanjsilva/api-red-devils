<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pelada>
 */
class PeladaFactory extends Factory
{
    /**
     * Define o estado padrão do modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'location' => fake()->streetAddress(),
            'qtd_times' => fake()->numberBetween(2, 4),
            'qtd_jogadores_por_time' => fake()->numberBetween(6, 10),
            'qtd_goleiros' => fake()->numberBetween(2, 4),
        ];
    }

    /**
     * Cria uma pelada para hoje.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Cria uma pelada para uma data específica.
     */
    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
