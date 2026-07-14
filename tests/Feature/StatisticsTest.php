<?php

namespace Tests\Feature;

use App\Models\MatchPlayer;
use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function pelada(string $monthDay, ?string $division = null): Pelada
    {
        $date = now()->year.'-'.$monthDay;
        $division ??= (int) date('N', strtotime($date)) === 6 ? 'sabado' : 'quinta';

        return Pelada::factory()->create(['date' => $date, 'division' => $division]);
    }

    /**
     * @return array<int, string>
     */
    private function weekdayDates(int $weekday, int $count): array
    {
        $date = Carbon::now()->startOfYear()->next($weekday);

        return collect(range(0, $count - 1))->map(fn ($i) => $date->copy()->addWeeks($i)->format('Y-m-d'))->all();
    }

    private function matchPlayer(Player $player, Pelada $pelada, array $attributes = []): MatchPlayer
    {
        return MatchPlayer::factory()->create(array_merge([
            'player_id' => $player->id,
            'pelada_id' => $pelada->id,
        ], $attributes));
    }

    public function test_dashboard_aggregates_totals_averages_and_leaders(): void
    {
        $player1 = Player::factory()->linha()->create();
        $player2 = Player::factory()->linha()->create();
        $goalkeeper = Player::factory()->goleiro()->create();

        $peladaA = $this->pelada('06-01');
        $peladaB = $this->pelada('06-08');

        $this->matchPlayer($player1, $peladaA, ['goals' => 3, 'assists' => 1, 'result' => 'win']);
        $this->matchPlayer($player2, $peladaA, ['goals' => 1, 'assists' => 2, 'result' => 'win']);
        $this->matchPlayer($goalkeeper, $peladaA, ['goals' => 0, 'assists' => 0, 'goals_conceded' => 1, 'result' => 'win']);

        $this->matchPlayer($player1, $peladaB, ['goals' => 0, 'assists' => 0, 'result' => 'loss']);
        $this->matchPlayer($player2, $peladaB, ['goals' => 0, 'assists' => 0, 'result' => 'win']);
        $this->matchPlayer($goalkeeper, $peladaB, ['goals' => 0, 'assists' => 0, 'goals_conceded' => 3, 'result' => 'loss']);

        $response = $this->getJson('/api/statistics/dashboard?year='.now()->year);

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(2, $data['total_peladas']);
        $this->assertSame(3, $data['total_players']);
        $this->assertSame(4, $data['total_goals']);
        $this->assertSame(3, $data['total_assists']);
        $this->assertSame(7, $data['total_goal_participations']);
        $this->assertEquals(2.0, $data['avg_goals_per_pelada']);
        $this->assertEquals(1.5, $data['avg_assists_per_pelada']);
        $this->assertEquals(3.0, $data['avg_players_per_pelada']);

        $this->assertSame($peladaA->id, $data['pelada_with_most_goals']['pelada_id']);
        $this->assertSame(4, $data['pelada_with_most_goals']['total_goals']);

        $this->assertSame($player1->id, $data['top_scorer']['player']['id']);
        $this->assertEquals(3.0, $data['top_scorer']['value']);

        $this->assertSame($player2->id, $data['top_assister']['player']['id']);
        $this->assertEquals(2.0, $data['top_assister']['value']);

        $this->assertSame($player1->id, $data['top_goal_participation']['player']['id']);

        $this->assertSame($player2->id, $data['most_wins']['player']['id']);
        $this->assertSame($player2->id, $data['best_win_rate']['player']['id']);
        $this->assertEquals(100.0, $data['best_win_rate']['value']);

        $this->assertSame($goalkeeper->id, $data['best_goalkeeper']['player']['id']);
        $this->assertEquals(2.0, $data['best_goalkeeper']['value']);

        $this->assertNull($data['best_duo']);
        $this->assertSame(1, $data['minimum_matches_for_leaders']);
    }

    public function test_dashboard_handles_empty_scope_without_division_by_zero(): void
    {
        $response = $this->getJson('/api/statistics/dashboard?year=2099');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(0, $data['total_peladas']);
        $this->assertEquals(0.0, $data['avg_goals_per_pelada']);
        $this->assertEquals(0.0, $data['avg_assists_per_pelada']);
        $this->assertEquals(0.0, $data['avg_players_per_pelada']);
        $this->assertNull($data['pelada_with_most_goals']);
        $this->assertNull($data['top_scorer']);
        $this->assertNull($data['best_goalkeeper']);
    }

    public function test_goals_ranking_orders_by_total_then_average_and_applies_minimum_matches(): void
    {
        $playerA = Player::factory()->linha()->create(['name' => 'Player A']);
        $playerB = Player::factory()->linha()->create(['name' => 'Player B']);
        $playerC = Player::factory()->linha()->create(['name' => 'Player C']);

        // 10 peladas no ano corrente => minimo = ceil(10 * 0.2) = 2 partidas.
        $peladas = collect(range(1, 10))->map(fn ($day) => $this->pelada(sprintf('01-%02d', $day)));

        // Player A: 2 partidas, 4 gols (media 2.0).
        $this->matchPlayer($playerA, $peladas[0], ['goals' => 2, 'result' => 'win']);
        $this->matchPlayer($playerA, $peladas[1], ['goals' => 2, 'result' => 'win']);

        // Player B: 4 partidas, 4 gols (media 1.0) - empata em total com A, mas perde no desempate por media.
        $this->matchPlayer($playerB, $peladas[2], ['goals' => 1, 'result' => 'win']);
        $this->matchPlayer($playerB, $peladas[3], ['goals' => 1, 'result' => 'win']);
        $this->matchPlayer($playerB, $peladas[4], ['goals' => 1, 'result' => 'loss']);
        $this->matchPlayer($playerB, $peladas[5], ['goals' => 1, 'result' => 'loss']);

        // Player C: só 1 partida (abaixo do mínimo de 2) - deve ficar de fora do ranking de gols.
        $this->matchPlayer($playerC, $peladas[6], ['goals' => 10, 'result' => 'win']);

        $response = $this->getJson('/api/statistics/rankings/goals?year='.now()->year);

        $response->assertOk();
        $data = $response->json('data');
        $meta = $response->json('meta');

        $this->assertSame(2, $meta['minimum_matches']);
        $this->assertCount(2, $data);

        $this->assertSame(1, $data[0]['position']);
        $this->assertSame($playerA->id, $data[0]['player']['id']);
        $this->assertEquals(4.0, $data[0]['value']);
        $this->assertEquals(2.0, $data[0]['average_per_match']);

        $this->assertSame(2, $data[1]['position']);
        $this->assertSame($playerB->id, $data[1]['player']['id']);
        $this->assertEquals(4.0, $data[1]['value']);
        $this->assertEquals(1.0, $data[1]['average_per_match']);

        $ids = collect($data)->pluck('player.id');
        $this->assertFalse($ids->contains($playerC->id));
    }

    public function test_appearances_ranking_does_not_apply_minimum_matches_by_default(): void
    {
        $playerC = Player::factory()->linha()->create();
        $pelada = $this->pelada('02-01');

        $this->matchPlayer($playerC, $pelada, ['goals' => 1, 'result' => 'win']);

        // Sem outras peladas no ano, o minimo calculado seria pequeno; ainda assim
        // o ranking de presenças não deve excluir ninguém por padrão.
        $response = $this->getJson('/api/statistics/rankings/appearances?year='.now()->year);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('player.id');
        $this->assertTrue($ids->contains($playerC->id));
    }

    public function test_minimum_matches_query_parameter_overrides_the_computed_default(): void
    {
        $player = Player::factory()->linha()->create();
        $pelada = $this->pelada('03-01');
        $this->matchPlayer($player, $pelada, ['goals' => 1, 'result' => 'win']);

        $response = $this->getJson('/api/statistics/rankings/goals?year='.now()->year.'&minimum_matches=5');

        $response->assertOk();
        $this->assertSame(5, $response->json('meta.minimum_matches'));
        $this->assertCount(0, $response->json('data'));
    }

    public function test_player_statistics_computes_streaks_percentages_and_attendance(): void
    {
        $player = Player::factory()->linha()->create();

        // Peladas em sequência: marcou, marcou, não marcou, marcou, marcou, marcou (streak de 3).
        $results = [
            ['day' => '04-01', 'goals' => 1, 'assists' => 0, 'result' => 'win'],
            ['day' => '04-02', 'goals' => 2, 'assists' => 0, 'result' => 'win'],
            ['day' => '04-03', 'goals' => 0, 'assists' => 0, 'result' => 'loss'],
            ['day' => '04-04', 'goals' => 1, 'assists' => 1, 'result' => 'draw'],
            ['day' => '04-05', 'goals' => 1, 'assists' => 0, 'result' => 'win'],
            ['day' => '04-06', 'goals' => 3, 'assists' => 0, 'result' => 'win'],
        ];

        foreach ($results as $r) {
            $this->matchPlayer($player, $this->pelada($r['day']), [
                'goals' => $r['goals'], 'assists' => $r['assists'], 'result' => $r['result'],
            ]);
        }

        $response = $this->getJson('/api/statistics/players/'.$player->id.'?year='.now()->year);

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(6, $data['total_matches']);
        $this->assertSame(8, $data['total_goals']);
        $this->assertSame(1, $data['total_assists']);
        $this->assertSame(4, $data['total_wins']);
        $this->assertSame(1, $data['total_losses']);
        $this->assertSame(1, $data['total_draws']);

        // Marcou em 5 dos 6 jogos.
        $this->assertSame(round(5 / 6 * 100, 2), $data['pct_matches_scoring']);

        // Melhor sequência marcando: os últimos 3 jogos (04-04, 04-05, 04-06).
        $this->assertSame(3, $data['best_scoring_streak']);

        // Sequência de invencibilidade: últimos 3 jogos sem derrota (draw, win, win).
        $this->assertSame(3, $data['best_unbeaten_streak']);

        // Assiduidade: 6 peladas disputadas desde a primeira (6 peladas criadas no total), 100%.
        $this->assertEquals(100.0, $data['attendance_rate']);

        $this->assertSame(5, count($data['recent_form']['matches']));
    }

    public function test_recent_form_classifies_rising_trend(): void
    {
        $player = Player::factory()->linha()->create();

        // Janela de 5: primeira metade fraca, segunda metade forte -> tendencia de alta.
        $sequence = [
            ['day' => '05-01', 'goals' => 0],
            ['day' => '05-02', 'goals' => 0],
            ['day' => '05-03', 'goals' => 3],
            ['day' => '05-04', 'goals' => 3],
            ['day' => '05-05', 'goals' => 3],
        ];

        foreach ($sequence as $s) {
            $this->matchPlayer($player, $this->pelada($s['day']), ['goals' => $s['goals'], 'assists' => 0, 'result' => 'win']);
        }

        $response = $this->getJson('/api/statistics/recent-form?last_matches=5&year='.now()->year);

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('player.id', $player->id);

        $this->assertNotNull($item);
        $this->assertSame('alta', $item['trend']);
        $this->assertSame(9, $item['goals']);
    }

    public function test_compare_players_normalizes_radar_values_relative_to_the_max(): void
    {
        $player1 = Player::factory()->linha()->create();
        $player2 = Player::factory()->linha()->create();

        $pelada = $this->pelada('07-01');
        $this->matchPlayer($player1, $pelada, ['goals' => 4, 'assists' => 0, 'result' => 'win']);
        $this->matchPlayer($player2, $pelada, ['goals' => 2, 'assists' => 0, 'result' => 'loss']);

        $response = $this->getJson('/api/statistics/players/compare?player_ids[]='.$player1->id.'&player_ids[]='.$player2->id.'&year='.now()->year);

        $response->assertOk();
        $data = collect($response->json('data'));

        $item1 = $data->firstWhere('player.id', $player1->id);
        $item2 = $data->firstWhere('player.id', $player2->id);

        $this->assertEquals(100.0, $item1['radar']['total_goals']);
        $this->assertEquals(50.0, $item2['radar']['total_goals']);
    }

    public function test_compare_players_requires_at_least_two_valid_players(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson('/api/statistics/players/compare?player_ids[]='.$player->id);

        $response->assertStatus(422);
    }

    public function test_match_statistics_computes_team_results_and_goal_difference(): void
    {
        $pelada = $this->pelada('08-01');

        $teamA = Team::factory()->create(['pelada_id' => $pelada->id, 'name' => 'Time A']);
        $teamB = Team::factory()->create(['pelada_id' => $pelada->id, 'name' => 'Time B']);

        $playerA1 = Player::factory()->linha()->create();
        $playerA2 = Player::factory()->linha()->create();
        $playerB1 = Player::factory()->linha()->create();

        TeamPlayer::create(['team_id' => $teamA->id, 'player_id' => $playerA1->id]);
        TeamPlayer::create(['team_id' => $teamA->id, 'player_id' => $playerA2->id]);
        TeamPlayer::create(['team_id' => $teamB->id, 'player_id' => $playerB1->id]);

        $this->matchPlayer($playerA1, $pelada, ['goals' => 2, 'assists' => 1, 'result' => 'win']);
        $this->matchPlayer($playerA2, $pelada, ['goals' => 1, 'assists' => 0, 'result' => 'win']);
        $this->matchPlayer($playerB1, $pelada, ['goals' => 1, 'assists' => 0, 'result' => 'loss']);

        $response = $this->getJson('/api/statistics/matches/'.$pelada->id);

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(3, $data['total_players']);
        $this->assertSame(4, $data['total_goals']);
        $this->assertSame($playerA1->id, $data['top_scorer']['player']['id']);
        $this->assertSame($playerA1->id, $data['top_goal_participation']['player']['id']);

        $teamResults = collect($data['team_results'])->keyBy('team_id');
        $this->assertSame(3, $teamResults[$teamA->id]['total_goals']);
        $this->assertSame(1, $teamResults[$teamB->id]['total_goals']);
        $this->assertSame(2, $data['goal_difference']);
    }

    public function test_evolution_groups_by_month_and_year(): void
    {
        $player = Player::factory()->linha()->create();

        $this->matchPlayer($player, $this->pelada('01-10'), ['goals' => 2, 'result' => 'win']);
        $this->matchPlayer($player, $this->pelada('01-20'), ['goals' => 1, 'result' => 'win']);
        $this->matchPlayer($player, $this->pelada('02-05'), ['goals' => 3, 'result' => 'win']);

        $byMonth = $this->getJson('/api/statistics/evolution?group_by=month&year='.now()->year)->json('data');
        $this->assertCount(2, $byMonth);
        $january = collect($byMonth)->firstWhere('period', now()->year.'-01');
        $this->assertSame(3, $january['total_goals']);
        $this->assertSame(2, $january['total_peladas']);

        $byYear = $this->getJson('/api/statistics/evolution?group_by=year&year='.now()->year)->json('data');
        $this->assertCount(1, $byYear);
        $this->assertSame(6, $byYear[0]['total_goals']);
        $this->assertSame(3, $byYear[0]['total_peladas']);
    }

    public function test_best_duo_is_computed_from_shared_team_wins(): void
    {
        $playerA = Player::factory()->linha()->create();
        $playerB = Player::factory()->linha()->create();

        $peladaA = $this->pelada('09-01');
        $peladaB = $this->pelada('09-08');

        $teamA1 = Team::factory()->create(['pelada_id' => $peladaA->id]);
        $teamA2 = Team::factory()->create(['pelada_id' => $peladaB->id]);

        TeamPlayer::create(['team_id' => $teamA1->id, 'player_id' => $playerA->id]);
        TeamPlayer::create(['team_id' => $teamA1->id, 'player_id' => $playerB->id]);
        TeamPlayer::create(['team_id' => $teamA2->id, 'player_id' => $playerA->id]);
        TeamPlayer::create(['team_id' => $teamA2->id, 'player_id' => $playerB->id]);

        $this->matchPlayer($playerA, $peladaA, ['result' => 'win']);
        $this->matchPlayer($playerB, $peladaA, ['result' => 'win']);
        $this->matchPlayer($playerA, $peladaB, ['result' => 'win']);
        $this->matchPlayer($playerB, $peladaB, ['result' => 'win']);

        $response = $this->getJson('/api/statistics/players/'.$playerA->id.'?year='.now()->year);

        $response->assertOk();
        $duo = $response->json('data.best_duo');

        $this->assertNotNull($duo);
        $this->assertSame(2, $duo['matches_together']);
        $this->assertSame(2, $duo['wins_together']);
        $this->assertEquals(100.0, $duo['win_rate_together']);
        $this->assertTrue(collect($duo['players'])->pluck('id')->contains($playerB->id));
    }

    public function test_goalkeepers_overview_returns_only_goalkeepers(): void
    {
        $goalkeeper = Player::factory()->goleiro()->create();
        $fieldPlayer = Player::factory()->linha()->create();
        $pelada = $this->pelada('10-01');

        $this->matchPlayer($goalkeeper, $pelada, ['goals' => 0, 'assists' => 0, 'goals_conceded' => 2, 'result' => 'win']);
        $this->matchPlayer($fieldPlayer, $pelada, ['goals' => 1, 'assists' => 0, 'result' => 'win']);

        $response = $this->getJson('/api/statistics/goalkeepers?year='.now()->year);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('player.id');

        $this->assertTrue($ids->contains($goalkeeper->id));
        $this->assertFalse($ids->contains($fieldPlayer->id));
    }

    public function test_goalkeeper_statistics_rejects_non_goalkeeper_player(): void
    {
        $fieldPlayer = Player::factory()->linha()->create();

        $response = $this->getJson('/api/statistics/goalkeepers/'.$fieldPlayer->id);

        $response->assertStatus(422);
    }

    public function test_null_goals_and_assists_are_treated_as_zero_in_participation_totals(): void
    {
        $goalkeeper = Player::factory()->goleiro()->create();
        $pelada = $this->pelada('11-01');

        // Goleiro sem goals/assists registrados (nulo) - não deve quebrar nem
        // subestimar a soma de participações de outros jogadores na mesma pelada.
        MatchPlayer::factory()->create([
            'player_id' => $goalkeeper->id,
            'pelada_id' => $pelada->id,
            'goals' => null,
            'assists' => null,
            'goals_conceded' => 2,
            'result' => 'win',
        ]);

        $response = $this->getJson('/api/statistics/rankings/goalkeepers?year='.now()->year);

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('player.id', $goalkeeper->id);

        $this->assertNotNull($item);
        $this->assertSame(0, $item['goals']);
        $this->assertSame(0, $item['assists']);
    }

    public function test_division_filter_isolates_statistics_between_thursday_and_saturday(): void
    {
        $playerQuinta = Player::factory()->linha()->create();
        $playerSabado = Player::factory()->linha()->create();

        $quintaDates = $this->weekdayDates(Carbon::THURSDAY, 2);
        $sabadoDate = $this->weekdayDates(Carbon::SATURDAY, 1)[0];

        $peladaQ1 = Pelada::factory()->create(['date' => $quintaDates[0], 'division' => 'quinta']);
        $peladaQ2 = Pelada::factory()->create(['date' => $quintaDates[1], 'division' => 'quinta']);
        $peladaS1 = Pelada::factory()->create(['date' => $sabadoDate, 'division' => 'sabado']);

        $this->matchPlayer($playerQuinta, $peladaQ1, ['goals' => 3, 'result' => 'win']);
        $this->matchPlayer($playerQuinta, $peladaQ2, ['goals' => 1, 'result' => 'win']);
        $this->matchPlayer($playerSabado, $peladaS1, ['goals' => 5, 'result' => 'win']);

        $quinta = $this->getJson('/api/statistics/dashboard?division=quinta&year='.now()->year)->json('data');
        $this->assertSame(2, $quinta['total_peladas']);
        $this->assertSame(4, $quinta['total_goals']);
        $this->assertSame($playerQuinta->id, $quinta['top_scorer']['player']['id']);

        $sabado = $this->getJson('/api/statistics/dashboard?division=sabado&year='.now()->year)->json('data');
        $this->assertSame(1, $sabado['total_peladas']);
        $this->assertSame(5, $sabado['total_goals']);
        $this->assertSame($playerSabado->id, $sabado['top_scorer']['player']['id']);

        $combined = $this->getJson('/api/statistics/dashboard?year='.now()->year)->json('data');
        $this->assertSame(3, $combined['total_peladas']);
        $this->assertSame(9, $combined['total_goals']);
    }

    public function test_minimum_matches_is_computed_separately_per_division(): void
    {
        $player = Player::factory()->linha()->create();

        // 10 peladas de quinta => minimo na divisão quinta = ceil(10*0.2) = 2.
        $quintaDates = $this->weekdayDates(Carbon::THURSDAY, 10);
        foreach ($quintaDates as $date) {
            Pelada::factory()->create(['date' => $date, 'division' => 'quinta']);
        }

        // 2 peladas de sábado => minimo na divisão sabado = ceil(2*0.2) = 1.
        $sabadoDates = $this->weekdayDates(Carbon::SATURDAY, 2);
        $peladaS1 = Pelada::factory()->create(['date' => $sabadoDates[0], 'division' => 'sabado']);
        Pelada::factory()->create(['date' => $sabadoDates[1], 'division' => 'sabado']);

        // O jogador só disputou 1 das 10 peladas de quinta (abaixo do mínimo de 2).
        $this->matchPlayer($player, Pelada::where('date', $quintaDates[0])->first(), ['goals' => 5, 'result' => 'win']);

        // E disputou 1 das 2 peladas de sábado (dentro do mínimo de 1 daquela divisão).
        $this->matchPlayer($player, $peladaS1, ['goals' => 5, 'result' => 'win']);

        $quintaRanking = $this->getJson('/api/statistics/rankings/goals?division=quinta&year='.now()->year);
        $this->assertSame(2, $quintaRanking->json('meta.minimum_matches'));
        $this->assertFalse(collect($quintaRanking->json('data'))->pluck('player.id')->contains($player->id));

        $sabadoRanking = $this->getJson('/api/statistics/rankings/goals?division=sabado&year='.now()->year);
        $this->assertSame(1, $sabadoRanking->json('meta.minimum_matches'));
        $this->assertTrue(collect($sabadoRanking->json('data'))->pluck('player.id')->contains($player->id));
    }
}
