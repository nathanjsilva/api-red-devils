<?php

namespace Tests\Feature;

use App\Models\MatchPlayer;
use App\Models\Pelada;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerPeladaHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function pelada(string $monthDay): Pelada
    {
        $date = now()->year.'-'.$monthDay;
        $division = (int) date('N', strtotime($date)) === 6 ? 'sabado' : 'quinta';

        return Pelada::factory()->create(['date' => $date, 'division' => $division]);
    }

    private function matchPlayer(Player $player, Pelada $pelada, array $attributes = []): MatchPlayer
    {
        return MatchPlayer::factory()->create(array_merge([
            'player_id' => $player->id,
            'pelada_id' => $pelada->id,
        ], $attributes));
    }

    public function test_pelada_history_flags_top_scorer_and_top_assister_of_each_pelada(): void
    {
        $player1 = Player::factory()->linha()->create();
        $player2 = Player::factory()->linha()->create();

        $peladaA = $this->pelada('06-01');
        $peladaB = $this->pelada('06-08');

        $this->matchPlayer($player1, $peladaA, ['goals' => 3, 'assists' => 0, 'result' => 'win']);
        $this->matchPlayer($player2, $peladaA, ['goals' => 1, 'assists' => 2, 'result' => 'loss']);

        $this->matchPlayer($player1, $peladaB, ['goals' => 0, 'assists' => 0, 'result' => 'loss']);
        $this->matchPlayer($player2, $peladaB, ['goals' => 1, 'assists' => 0, 'result' => 'win']);

        $response = $this->getJson('/api/statistics/players/'.$player1->id);

        $response->assertOk();
        $history = collect($response->json('data.pelada_history'))->keyBy('pelada_id');

        // player1 fez 3 gols na pelada A (mais que os 1 do player2) -> artilheiro do dia; nao deu assistencia -> nao e garcom.
        $this->assertTrue($history[$peladaA->id]['is_top_scorer']);
        $this->assertFalse($history[$peladaA->id]['is_top_assister']);

        // player1 nao marcou nem assistiu na pelada B -> nenhum selo.
        $this->assertFalse($history[$peladaB->id]['is_top_scorer']);
        $this->assertFalse($history[$peladaB->id]['is_top_assister']);

        // pelada_history vem sem goals_conceded pra jogador de linha.
        $this->assertNull($history[$peladaA->id]['goals_conceded']);

        // mais recente primeiro.
        $orderedIds = collect($response->json('data.pelada_history'))->pluck('pelada_id')->all();
        $this->assertSame([$peladaB->id, $peladaA->id], $orderedIds);
    }

    public function test_pelada_history_includes_goals_conceded_for_goalkeeper(): void
    {
        $goalkeeper = Player::factory()->goleiro()->create();
        $pelada = $this->pelada('06-01');

        $this->matchPlayer($goalkeeper, $pelada, ['goals' => 0, 'assists' => 0, 'goals_conceded' => 2, 'result' => 'loss']);

        $response = $this->getJson('/api/statistics/players/'.$goalkeeper->id);

        $response->assertOk();
        $entry = collect($response->json('data.pelada_history'))->first();

        $this->assertSame(2, $entry['goals_conceded']);
    }

    public function test_evolution_includes_total_goals_conceded_per_period(): void
    {
        $goalkeeper = Player::factory()->goleiro()->create();
        $pelada = $this->pelada('06-01');

        $this->matchPlayer($goalkeeper, $pelada, ['goals' => 0, 'assists' => 0, 'goals_conceded' => 4, 'result' => 'loss']);

        $response = $this->getJson('/api/statistics/players/'.$goalkeeper->id);

        $response->assertOk();
        $point = collect($response->json('data.evolution'))->first();

        $this->assertSame(4, $point['total_goals_conceded']);
    }
}
