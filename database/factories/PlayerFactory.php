<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define o estado padrão do modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'nickname' => fake()->unique()->userName(),
            'position' => fake()->randomElement(['linha', 'goleiro']),
        ];
    }

    /**
     * Cria um jogador com posição linha.
     */
    public function linha(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'linha',
        ]);
    }

    /**
     * Cria um jogador com posição goleiro.
     */
    public function goleiro(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'goleiro',
        ]);
    }
}
