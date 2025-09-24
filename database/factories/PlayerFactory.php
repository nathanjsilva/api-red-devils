<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    /**
     * A senha atual sendo usada pela factory.
     */
    protected static ?string $password;

    /**
     * Define o estado padrão do modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'position' => fake()->randomElement(['linha', 'goleiro']),
            'phone' => fake()->unique()->phoneNumber(),
            'nickname' => fake()->unique()->userName(),
        ];
    }

    /**
     * Indica que o endereço de e-mail do modelo deve ser não verificado.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
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
