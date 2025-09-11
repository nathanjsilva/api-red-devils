<?php

namespace Tests\Feature;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_login_with_valid_credentials()
    {
        $player = Player::factory()->create([
            'email' => 'test@test.com',
            'password' => Hash::make('123456')
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@test.com',
            'password' => '123456'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'access_token',
                        'token_type',
                        'player' => [
                            'id',
                            'name',
                            'email',
                            'position'
                        ]
                    ]
                ]);

        $this->assertEquals('Bearer', $response->json('data.token_type'));
    }

    public function test_cannot_login_with_invalid_credentials()
    {
        Player::factory()->create([
            'email' => 'test@test.com',
            'password' => Hash::make('123456')
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@test.com',
            'password' => 'wrong_password'
        ]);

        $response->assertStatus(401)
                ->assertJson(['message' => 'Credenciais inválidas']);
    }

    public function test_cannot_login_with_nonexistent_email()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@test.com',
            'password' => '123456'
        ]);

        $response->assertStatus(401)
                ->assertJson(['message' => 'Credenciais inválidas']);
    }

    public function test_login_requires_email_and_password()
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
    }
}
