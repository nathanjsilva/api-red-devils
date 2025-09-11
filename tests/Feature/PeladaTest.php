<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Pelada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeladaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_pelada()
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;

        $peladaData = [
            'date' => now()->addDays(7)->format('Y-m-d')
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/peladas', $peladaData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'date',
                        'created_at',
                        'updated_at'
                    ]
                ]);

        $this->assertDatabaseHas('peladas', [
            'date' => now()->addDays(7)->format('Y-m-d')
        ]);
    }

    public function test_cannot_create_pelada_with_past_date()
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;

        $peladaData = [
            'date' => '2020-01-01'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/peladas', $peladaData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['date']);
    }

    public function test_can_get_peladas_list()
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;

        Pelada::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/peladas');

        $response->assertStatus(200)
                ->assertJsonCount(3, 'data');
    }

    public function test_can_get_single_pelada()
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;

        $pelada = Pelada::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/peladas/{$pelada->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'id' => $pelada->id,
                        'date' => $pelada->date
                    ]
                ]);
    }

    public function test_can_update_pelada()
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;

        $pelada = Pelada::factory()->create();

        $updateData = [
            'date' => now()->addDays(14)->format('Y-m-d')
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/peladas/{$pelada->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'date' => now()->addDays(14)->format('Y-m-d')
                    ]
                ]);

        $this->assertDatabaseHas('peladas', [
            'id' => $pelada->id,
            'date' => now()->addDays(14)->format('Y-m-d')
        ]);
    }

    public function test_can_delete_pelada()
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;

        $pelada = Pelada::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/peladas/{$pelada->id}");

        $response->assertStatus(200)
                ->assertJson(['message' => 'Pelada deletada com sucesso.']);

        $this->assertDatabaseMissing('peladas', ['id' => $pelada->id]);
    }
}
