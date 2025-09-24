<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Pelada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MatchPlayer>
 */
class MatchPlayerFactory extends Factory
{
    /**
     * Define o estado padrão do modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'pelada_id' => Pelada::factory(),
            'goals' => fake()->numberBetween(0, 5),
            'assists' => fake()->numberBetween(0, 3),
            'goals_conceded' => fake()->numberBetween(0, 3),
            'is_winner' => fake()->boolean(),
        ];
    }

    /**
     * Cria um jogador de partida para um goleiro.
     */
    public function goleiro(): static
    {
        return $this->state(function (array $attributes) {
            $goleiro = Player::factory()->goleiro()->create();
            return [
                'player_id' => $goleiro->id,
                'goals_conceded' => fake()->numberBetween(0, 5),
            ];
        });
    }

    /**
     * Cria um jogador de partida para um jogador de linha.
     */
    public function linha(): static
    {
        return $this->state(function (array $attributes) {
            $linha = Player::factory()->linha()->create();
            return [
                'player_id' => $linha->id,
                'goals_conceded' => null,
            ];
        });
    }

    /**
     * Cria um jogador de partida vencedor.
     */
    public function winner(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_winner' => true,
        ]);
    }

    /**
     * Cria um jogador de partida perdedor.
     */
    public function loser(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_winner' => false,
        ]);
    }
}
