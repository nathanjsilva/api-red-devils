<?php

namespace Tests\Feature;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PlayerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_can_create_player()
    {
        $playerData = [
            'name' => 'João Silva',
            'email' => 'joao@test.com',
            'password' => '123456',
            'position' => 'linha'
        ];

        $response = $this->postJson('/api/players', $playerData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'position',
                        'created_at',
                        'updated_at'
                    ]
                ]);

        $this->assertDatabaseHas('players', [
            'name' => 'João Silva',
            'email' => 'joao@test.com',
            'position' => 'linha'
        ]);
    }

    public function test_cannot_create_player_with_duplicate_email()
    {
        Player::factory()->create(['email' => 'test@test.com']);

        $playerData = [
            'name' => 'João Silva',
            'email' => 'test@test.com',
            'password' => '123456',
            'position' => 'linha'
        ];

        $response = $this->postJson('/api/players', $playerData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_create_player_with_invalid_position()
    {
        $playerData = [
            'name' => 'João Silva',
            'email' => 'joao@test.com',
            'password' => '123456',
            'position' => 'invalid_position'
        ];

        $response = $this->postJson('/api/players', $playerData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['position']);
    }

    public function test_can_get_players_list()
    {
        Player::factory()->count(3)->create();

        $response = $this->getJson('/api/players');

        $response->assertStatus(401); // Não autenticado
    }

    public function test_can_get_authenticated_players_list()
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;

        Player::factory()->count(2)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/players');

        $response->assertStatus(200)
                ->assertJsonCount(3, 'data');
    }

    public function test_can_get_single_player()
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/players/{$player->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'id' => $player->id,
                        'name' => $player->name,
                        'email' => $player->email,
                        'position' => $player->position,
                    ]
                ]);
    }

    public function test_can_update_player()
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;

        $updateData = [
            'name' => 'João Silva Atualizado',
            'position' => 'goleiro'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/players/{$player->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'name' => 'João Silva Atualizado',
                        'position' => 'goleiro'
                    ]
                ]);

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'name' => 'João Silva Atualizado',
            'position' => 'goleiro'
        ]);
    }

    public function test_can_delete_player()
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/players/{$player->id}");

        $response->assertStatus(200)
                ->assertJson(['message' => 'Jogador deletado com sucesso.']);

        $this->assertDatabaseMissing('players', ['id' => $player->id]);
    }
}
