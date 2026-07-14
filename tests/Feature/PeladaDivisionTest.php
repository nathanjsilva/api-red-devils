<?php

namespace Tests\Feature;

use App\Models\Pelada;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeladaDivisionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['profile' => 'admin']);
    }

    private function nextWeekday(int $weekday): string
    {
        return Carbon::now()->next($weekday)->format('Y-m-d');
    }

    public function test_store_pelada_requires_division(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/admin/peladas', [
            'date' => $this->nextWeekday(Carbon::THURSDAY),
            'location' => 'Quadra 1',
            'qtd_times' => 2,
            'qtd_jogadores_por_time' => 6,
            'qtd_goleiros' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('division');
    }

    public function test_store_pelada_rejects_date_that_does_not_match_division_weekday(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/admin/peladas', [
            'date' => $this->nextWeekday(Carbon::FRIDAY),
            'division' => 'quinta',
            'location' => 'Quadra 1',
            'qtd_times' => 2,
            'qtd_jogadores_por_time' => 6,
            'qtd_goleiros' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('division');
    }

    public function test_store_pelada_accepts_matching_date_and_division(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/admin/peladas', [
            'date' => $this->nextWeekday(Carbon::SATURDAY),
            'division' => 'sabado',
            'location' => 'Quadra 1',
            'qtd_times' => 2,
            'qtd_jogadores_por_time' => 6,
            'qtd_goleiros' => 2,
        ]);

        $response->assertCreated();
        $this->assertSame('sabado', $response->json('data.division'));
    }

    public function test_update_pelada_validates_division_against_the_existing_date(): void
    {
        $pelada = Pelada::factory()->create([
            'date' => $this->nextWeekday(Carbon::THURSDAY),
            'division' => 'quinta',
        ]);

        // Só troca a divisão para "sabado", sem trocar a data (que continua sendo quinta-feira).
        $response = $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/peladas/'.$pelada->id, [
            'division' => 'sabado',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('division');
    }

    public function test_update_pelada_allows_changing_division_together_with_a_matching_date(): void
    {
        $pelada = Pelada::factory()->create([
            'date' => $this->nextWeekday(Carbon::THURSDAY),
            'division' => 'quinta',
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/peladas/'.$pelada->id, [
            'date' => $this->nextWeekday(Carbon::SATURDAY),
            'division' => 'sabado',
        ]);

        $response->assertOk();
        $this->assertSame('sabado', $response->json('data.division'));
    }
}
