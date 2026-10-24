<?php

namespace Tests\Feature;

use App\Models\Championship;
use App\Models\ChampionshipAwardCategory;
use App\Models\ChampionshipMatch;
use App\Models\ChampionshipMatchEvent;
use App\Models\ChampionshipMatchParticipation;
use App\Models\ChampionshipOdds;
use App\Models\ChampionshipPlayer;
use App\Models\ChampionshipTeam;
use App\Models\ChampionshipVote;
use App\Models\ChampionshipVoter;
use App\Models\Player;
use App\Models\User;
use App\Services\ChampionshipAwardService;
use App\Services\ChampionshipFixtureService;
use App\Services\ChampionshipOddsService;
use App\Services\ChampionshipPlayoffService;
use App\Services\ChampionshipStandingsService;
use App\Services\ChampionshipValidationService;
use App\Support\ChampionshipE2EEnvironment;
use Database\Seeders\ChampionshipE2ECleanup;
use Database\Seeders\ChampionshipE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

class ChampionshipWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_keeps_thirteen_available_awards_and_archives_two(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/admin/championships', [
            'name' => 'Campeonato de Teste',
        ]);

        $response->assertCreated();
        $championshipId = $response->json('id');
        $this->assertSame(5, Championship::findOrFail($championshipId)->teams()->count());
        $this->assertSame(13, ChampionshipAwardCategory::where('championship_id', $championshipId)->count());
        $this->assertSame(13, ChampionshipAwardCategory::where('championship_id', $championshipId)->available()->count());
        $this->assertSame(0, ChampionshipAwardCategory::where('championship_id', $championshipId)->where('is_active', false)->count());
        $this->assertSame(13, ChampionshipAwardCategory::where('championship_id', $championshipId)->where('type', 'vote')->where('voting_open', true)->count());
        $this->assertSame(2, ChampionshipAwardCategory::where('championship_id', $championshipId)->where('type', 'statistic')->count());
    }

    public function test_admin_championship_routes_reject_guests_and_non_admins(): void
    {
        $this->postJson('/api/admin/championships')->assertUnauthorized();

        $nonAdmin = (new User)->forceFill(['id' => 99999, 'profile' => 'common']);
        $this->actingAs($nonAdmin, 'sanctum')
            ->postJson('/api/admin/championships', ['name' => 'Sem permissão'])
            ->assertForbidden();
    }

    public function test_e2e_seed_and_cleanup_are_blocked_in_testing_environment_even_with_opt_in(): void
    {
        config()->set('championship_e2e.enabled', true);
        $this->expectException(RuntimeException::class);

        app(ChampionshipE2ESeeder::class)->run(1);
    }

    public function test_e2e_cleanup_is_blocked_in_testing_environment_even_with_opt_in(): void
    {
        config()->set('championship_e2e.enabled', true);
        $this->expectException(RuntimeException::class);

        app(ChampionshipE2ECleanup::class)->run(1);
    }

    public function test_e2e_guard_rejects_production_even_if_every_local_opt_in_is_set(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.url', 'http://localhost');
        config()->set('championship_e2e.enabled', true);
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', 'laravel');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.url', null);
        $this->expectException(RuntimeException::class);

        app(ChampionshipE2EEnvironment::class)->assertAllowed();
    }

    public function test_field_registration_checks_position_stone_group_and_unique_rating(): void
    {
        [$championship] = $this->makeChampionship();
        $admin = $this->admin();

        $validPlayer = Player::factory()->linha()->create();
        $this->actingAs($admin, 'sanctum')->postJson($this->playerRegistrationPath($championship), [
            'player_id' => $validPlayer->id,
            'player_type' => 'field',
            'stone_group' => 1,
            'stone_level' => 1.1,
        ])->assertCreated();

        $wrongPosition = Player::factory()->goleiro()->create();
        $this->postJson($this->playerRegistrationPath($championship), [
            'player_id' => $wrongPosition->id,
            'player_type' => 'field',
            'stone_group' => 1,
            'stone_level' => 1.2,
        ])->assertUnprocessable();

        $wrongGroup = Player::factory()->linha()->create();
        $this->postJson($this->playerRegistrationPath($championship), [
            'player_id' => $wrongGroup->id,
            'player_type' => 'field',
            'stone_group' => 2,
            'stone_level' => 1.2,
        ])->assertUnprocessable();

        $invalidBand = Player::factory()->linha()->create();
        $this->postJson($this->playerRegistrationPath($championship), [
            'player_id' => $invalidBand->id,
            'player_type' => 'field',
            'stone_group' => 1,
            'stone_level' => 1.9,
        ])->assertUnprocessable();

        $duplicateRating = Player::factory()->linha()->create();
        $this->postJson($this->playerRegistrationPath($championship), [
            'player_id' => $duplicateRating->id,
            'player_type' => 'field',
            'stone_group' => 1,
            'stone_level' => 1.1,
        ])->assertUnprocessable();

        $this->postJson($this->playerRegistrationPath($championship), [
            'player_id' => $validPlayer->id,
            'player_type' => 'field',
            'stone_group' => 1,
            'stone_level' => 1.3,
        ])->assertUnprocessable();
    }

    public function test_field_and_goalkeeper_registration_limits_are_enforced(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        $admin = $this->admin();

        for ($group = 1; $group <= 5; $group++) {
            for ($level = 1; $level <= 5; $level++) {
                $this->createFieldParticipant($championship, $group, sprintf('%d.%d', $group, $level));
            }
        }

        $this->actingAs($admin, 'sanctum')->postJson($this->playerRegistrationPath($championship), [
            'player_id' => Player::factory()->linha()->create()->id,
            'player_type' => 'field',
            'stone_group' => 1,
            'stone_level' => 1.1,
        ])->assertUnprocessable()->assertJsonPath('message', 'Limite de jogadores de linha atingido.');

        foreach ($teams as $index => $team) {
            $participant = $this->createGoalkeeperParticipant($championship, $index + 1);
            $team->players()->attach($participant->id);
        }

        $this->postJson($this->playerRegistrationPath($championship), [
            'player_id' => Player::factory()->goleiro()->create()->id,
            'player_type' => 'goalkeeper',
            'stone_group' => 1,
            'team_id' => $teams[0]->id,
        ])->assertUnprocessable()->assertJsonPath('message', 'Limite de goleiros atingido.');
    }

    public function test_goalkeeper_registration_requires_an_unoccupied_team_in_the_same_championship(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        [, $otherTeams] = $this->makeChampionship('Outro campeonato');
        $admin = $this->admin();
        $keeper = Player::factory()->goleiro()->create();
        $path = $this->playerRegistrationPath($championship);

        $this->actingAs($admin, 'sanctum')->postJson($path, [
            'player_id' => $keeper->id,
            'player_type' => 'goalkeeper',
            'stone_group' => 1,
        ])->assertUnprocessable();

        $this->postJson($path, [
            'player_id' => $keeper->id,
            'player_type' => 'goalkeeper',
            'stone_group' => 1,
            'team_id' => $otherTeams[0]->id,
        ])->assertUnprocessable();

        $this->postJson($path, [
            'player_id' => $keeper->id,
            'player_type' => 'goalkeeper',
            'stone_group' => 1,
            'team_id' => $teams[0]->id,
        ])->assertCreated();

        $this->postJson($path, [
            'player_id' => Player::factory()->goleiro()->create()->id,
            'player_type' => 'goalkeeper',
            'stone_group' => 2,
            'team_id' => $teams[0]->id,
        ])->assertUnprocessable()->assertJsonPath('message', 'A equipe já possui um goleiro.');
    }

    public function test_assignment_accepts_a_complete_balanced_roster_and_preserves_goalkeepers(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        $roster = $this->createCompleteRoster($championship, $teams);

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson(
            "/api/admin/championships/{$championship->id}/assignments",
            ['assignments' => $roster['assignments']],
        );

        $response->assertOk();
        $this->assertSame(30, (int) $championship->teams()->withCount('players')->get()->sum('players_count'));
        foreach ($roster['goalkeepers'] as $index => $goalkeeper) {
            $this->assertSame([$teams[$index]->id], $goalkeeper->fresh('teams')->teams->pluck('id')->all());
        }
    }

    public function test_assignment_rejects_cross_championship_and_reused_players(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        $firstRoster = $this->createCompleteRoster($championship, $teams);
        [$otherChampionship, $otherTeams] = $this->makeChampionship('Outro');
        $otherRoster = $this->createCompleteRoster($otherChampionship, $otherTeams);
        $admin = $this->admin();
        $path = "/api/admin/championships/{$championship->id}/assignments";

        $foreignAssignments = $firstRoster['assignments'];
        $foreignAssignments[0]['championship_player_ids'][0] = $otherRoster['assignments'][0]['championship_player_ids'][0];
        $this->actingAs($admin, 'sanctum')->postJson($path, ['assignments' => $foreignAssignments])->assertUnprocessable();

        $reusedAssignments = $firstRoster['assignments'];
        $reusedAssignments[1]['championship_player_ids'][0] = $reusedAssignments[0]['championship_player_ids'][0];
        $this->postJson($path, ['assignments' => $reusedAssignments])->assertUnprocessable();

        $this->assertSame(5, $championship->teams()->withCount('players')->get()->sum('players_count'));
    }

    public function test_start_requires_five_teams_and_a_complete_roster_and_records_audit(): void
    {
        [$incomplete] = $this->makeChampionship();
        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/admin/championships/{$incomplete->id}/start")->assertUnprocessable();

        [$wrongTeamCount, $teams] = $this->makeChampionship('Only four teams');
        $this->createCompleteRoster($wrongTeamCount, $teams);
        $teams[4]->delete();
        $this->postJson("/api/admin/championships/{$wrongTeamCount->id}/start")->assertUnprocessable();

        [$ready, $readyTeams] = $this->makeChampionship('Pronto');
        $readyRoster = $this->createCompleteRoster($ready, $readyTeams);
        $this->postJson("/api/admin/championships/{$ready->id}/assignments", ['assignments' => $readyRoster['assignments']])->assertOk();
        $this->postJson("/api/admin/championships/{$ready->id}/start")->assertOk()->assertJsonPath('status', 'active');
        $this->assertDatabaseHas('championship_audit_logs', [
            'championship_id' => $ready->id,
            'action' => 'championship.started',
        ]);
        $this->postJson("/api/admin/championships/{$ready->id}/start")->assertUnprocessable();
    }

    public function test_fixtures_cannot_be_generated_before_championship_start(): void
    {
        [$championship] = $this->makeChampionship();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/championships/{$championship->id}/fixtures")
            ->assertUnprocessable();
        $this->assertSame(0, $championship->matches()->count());
    }

    public function test_fixture_generation_creates_ten_unique_pairs_in_five_rounds_and_is_idempotent(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        $roster = $this->createCompleteRoster($championship, $teams);
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/championships/{$championship->id}/assignments", ['assignments' => $roster['assignments']])->assertOk();
        $this->postJson("/api/admin/championships/{$championship->id}/start")->assertOk();
        $service = app(ChampionshipFixtureService::class);

        $service->generate($championship->fresh(), app(ChampionshipValidationService::class));
        $service->generate($championship->fresh(), app(ChampionshipValidationService::class));

        $matches = $championship->matches()->where('phase', 'group')->get();
        $pairs = $matches->map(fn (ChampionshipMatch $match) => min($match->home_team_id, $match->away_team_id).'-'.max($match->home_team_id, $match->away_team_id));
        $this->assertCount(10, $matches);
        $this->assertCount(10, $pairs->unique());
        $this->assertSame([2, 2, 2, 2, 2], $matches->groupBy('round')->map->count()->sortKeys()->values()->all());
        $this->assertSame(5, $matches->pluck('round')->unique()->count());
        $this->assertSame('active', $championship->fresh()->status);
    }

    public function test_admin_can_generate_and_recalculate_odds_before_start_without_changing_closed_match_history(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        $roster = $this->createCompleteRoster($championship, $teams);
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/championships/{$championship->id}/assignments", ['assignments' => $roster['assignments']])
            ->assertOk();

        $this->postJson("/api/admin/championships/{$championship->id}/fixtures")->assertOk();
        $this->assertSame('draft', $championship->fresh()->status);
        $this->assertSame(10, $championship->matches()->count());

        $this->postJson("/api/admin/championships/{$championship->id}/odds")
            ->assertOk()
            ->assertJsonPath('calculated_matches', 10);

        $matches = $championship->matches()->orderBy('id')->get();
        $firstOdds = $matches[0]->fresh('odds')->odds;
        $this->assertSame(1, $firstOdds->calculation_version);
        $this->assertEqualsWithDelta(1.0, (float) $firstOdds->home_probability + (float) $firstOdds->draw_probability + (float) $firstOdds->away_probability, 0.00001);
        $this->assertEqualsWithDelta(1 / (float) $firstOdds->home_probability, (float) $firstOdds->home_odd, 0.001);
        $this->assertEqualsWithDelta(1 / (float) $firstOdds->draw_probability, (float) $firstOdds->draw_odd, 0.001);
        $this->assertEqualsWithDelta(1 / (float) $firstOdds->away_probability, (float) $firstOdds->away_odd, 0.001);
        $this->assertTrue($firstOdds->parameters['preliminary']);
        $this->assertSame(0, $firstOdds->parameters['sample_matches']['league']);
        $this->assertSame(0, $firstOdds->parameters['sample_matches']['home_team']);

        $futureMatch = $matches->first(fn (ChampionshipMatch $match) => $match->id !== $matches[0]->id && $match->home_team_id === $matches[0]->home_team_id);
        $this->assertNotNull($futureMatch);
        $matches[0]->update(['status' => 'finished', 'home_score' => 2, 'away_score' => 0]);
        $matches[1]->update(['status' => 'cancelled']);
        $homeTeam = $championship->teams()->findOrFail($matches[0]->home_team_id);
        $homeField = $homeTeam->players()->where('player_type', 'field')->firstOrFail();
        $homeGoalkeeper = $homeTeam->players()->where('player_type', 'goalkeeper')->firstOrFail();
        ChampionshipMatchParticipation::create(['match_id' => $matches[0]->id, 'championship_player_id' => $homeField->id, 'mvp' => true]);
        ChampionshipMatchParticipation::create(['match_id' => $matches[0]->id, 'championship_player_id' => $homeGoalkeeper->id, 'mvp' => false]);
        ChampionshipMatchEvent::create(['match_id' => $matches[0]->id, 'scorer_id' => $homeField->id, 'team_id' => $matches[0]->home_team_id, 'own_goal' => false]);
        $finishedProbability = $firstOdds->home_probability;
        $cancelledOddsId = $matches[1]->odds()->value('id');

        $this->postJson("/api/admin/championships/{$championship->id}/odds")
            ->assertOk()
            ->assertJsonPath('calculated_matches', 8);

        $this->assertSame(1, ChampionshipOdds::where('match_id', $matches[0]->id)->count());
        $this->assertSame($finishedProbability, $matches[0]->fresh('odds')->odds->home_probability);
        $this->assertSame($cancelledOddsId, $matches[1]->fresh('odds')->odds->id);
        $this->assertSame(1, ChampionshipOdds::where('match_id', $matches[1]->id)->count());
        $this->assertSame(2, $futureMatch->fresh('odds')->odds->calculation_version);
        $this->assertSame(1, $futureMatch->fresh('odds')->odds->parameters['sample_matches']['league']);
        $this->assertSame(1, $futureMatch->fresh('odds')->odds->parameters['sample_matches']['home_field_players']);
        $this->assertSame(1, $futureMatch->fresh('odds')->odds->parameters['sample_matches']['home_goalkeepers']);
        $this->assertSame('draft', $championship->fresh()->status);
    }

    public function test_removed_award_categories_are_hidden_while_existing_votes_remain(): void
    {
        [$championship] = $this->makeChampionship();
        app(ChampionshipAwardService::class)->seed($championship);
        $candidate = $this->createFieldParticipant($championship, 1, '1.1');
        $voter = User::factory()->create();

        foreach (['Resenha do Campeonato', 'Inimigo do Gol'] as $name) {
            $category = ChampionshipAwardCategory::create([
                'championship_id' => $championship->id,
                'name' => $name,
                'description' => 'Categoria legada',
                'type' => 'vote',
                'published' => false,
                'voting_open' => false,
                'is_active' => false,
            ]);
            ChampionshipVote::create([
                'championship_award_category_id' => $category->id,
                'voter_id' => $voter->id,
                'candidate_id' => $candidate->id,
            ]);
        }

        $response = $this->getJson("/api/championships/{$championship->id}/awards")
            ->assertOk()
            ->assertJsonCount(13);

        $this->assertNotContains('Resenha do Campeonato', collect($response->json())->pluck('name')->all());
        $this->assertNotContains('Inimigo do Gol', collect($response->json())->pluck('name')->all());
        $this->assertTrue(collect($response->json())->every(fn (array $award) => filled($award['description'])));
        $this->assertSame(1, ChampionshipVote::whereHas('category', fn ($query) => $query->where('name', 'Resenha do Campeonato'))->count());
        $this->assertSame(1, ChampionshipVote::whereHas('category', fn ($query) => $query->where('name', 'Inimigo do Gol'))->count());
    }

    public function test_playoffs_cannot_be_generated_until_all_ten_group_matches_are_finished(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        $championship->update(['status' => 'active', 'phase' => 'group']);
        ChampionshipMatch::create([
            'championship_id' => $championship->id,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'phase' => 'group',
            'status' => 'finished',
            'home_score' => 1,
            'away_score' => 0,
        ]);

        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/admin/championships/{$championship->id}/playoffs")->assertUnprocessable();
        $this->assertSame(0, $championship->matches()->where('phase', 'semi_final')->count());
    }

    public function test_playoffs_use_the_expected_seedings_and_freeze_the_group_standings(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        $this->completeGroupMatchesInTeamOrder($championship, $teams);

        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/admin/championships/{$championship->id}/playoffs")->assertOk();

        $semifinals = $championship->matches()->where('phase', 'semi_final')->orderBy('id')->get();
        $this->assertCount(2, $semifinals);
        $this->assertSame([$teams[1]->id, $teams[4]->id], [$semifinals[0]->home_team_id, $semifinals[0]->away_team_id]);
        $this->assertSame([$teams[2]->id, $teams[3]->id], [$semifinals[1]->home_team_id, $semifinals[1]->away_team_id]);
        $this->assertSame('playoffs', $championship->fresh()->phase);
        $this->assertCount(5, $championship->fresh()->standings_snapshot);

        app(ChampionshipPlayoffService::class)->generate($championship->fresh());
        $this->assertSame(2, $championship->matches()->where('phase', 'semi_final')->count());
    }

    public function test_standings_count_only_finished_group_matches_and_apply_points(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        ChampionshipMatch::create([
            'championship_id' => $championship->id,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'phase' => 'group',
            'status' => 'finished',
            'home_score' => 3,
            'away_score' => 1,
        ]);
        ChampionshipMatch::create([
            'championship_id' => $championship->id,
            'home_team_id' => $teams[2]->id,
            'away_team_id' => $teams[3]->id,
            'phase' => 'group',
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 2,
        ]);
        ChampionshipMatch::create([
            'championship_id' => $championship->id,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[4]->id,
            'phase' => 'group',
            'status' => 'cancelled',
            'home_score' => 9,
            'away_score' => 0,
        ]);

        $table = collect(app(ChampionshipStandingsService::class)->calculate($championship))->keyBy('team_id');
        $this->assertSame(3, $table[$teams[0]->id]['points']);
        $this->assertSame(1, $table[$teams[0]->id]['played']);
        $this->assertSame(100.0, $table[$teams[0]->id]['win_rate']);
        $this->assertSame(1, $table[$teams[2]->id]['points']);
        $this->assertSame(0, $table[$teams[4]->id]['played']);
    }

    public function test_tie_breaker_rejects_teams_outside_its_championship(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        [, $otherTeams] = $this->makeChampionship('Outro');
        $championship->update(['phase' => 'group']);

        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/admin/championships/{$championship->id}/standings/tie-breaker", [
            'team_a_id' => $teams[0]->id,
            'team_b_id' => $otherTeams[0]->id,
            'winner_id' => $teams[0]->id,
        ])->assertUnprocessable();
    }

    public function test_match_events_recalculate_scores_and_own_goals_count_for_the_opponent(): void
    {
        $fixture = $this->makeMatchFixture();
        $admin = $this->admin();
        $path = "/api/admin/championship-matches/{$fixture['match']->id}/events";

        $goal = $this->actingAs($admin, 'sanctum')->postJson($path, [
            'team_id' => $fixture['home']->id,
            'scorer_id' => $fixture['homeScorer']->id,
            'assist_id' => $fixture['homeAssist']->id,
            'minute' => 14,
        ])->assertCreated()->json();
        $this->assertSame(1, $fixture['match']->fresh()->home_score);
        $this->assertSame(0, $fixture['match']->fresh()->away_score);

        $ownGoal = $this->postJson($path, [
            'team_id' => $fixture['away']->id,
            'scorer_id' => $fixture['awayScorer']->id,
            'own_goal' => true,
        ])->assertCreated()->json();
        $this->assertSame(2, $fixture['match']->fresh()->home_score);

        $this->putJson("/api/admin/championship-events/{$ownGoal['id']}", ['own_goal' => false])->assertOk();
        $this->assertSame([1, 1], [$fixture['match']->fresh()->home_score, $fixture['match']->fresh()->away_score]);

        $this->deleteJson("/api/admin/championship-events/{$goal['id']}")->assertOk();
        $this->assertSame([0, 1], [$fixture['match']->fresh()->home_score, $fixture['match']->fresh()->away_score]);
    }

    public function test_match_events_require_match_participants_and_valid_team_members(): void
    {
        $fixture = $this->makeMatchFixture();
        $extraHomePlayer = $this->createFieldParticipant($fixture['championship'], 3, '3.1');
        $fixture['home']->players()->attach($extraHomePlayer->id);
        $path = "/api/admin/championship-matches/{$fixture['match']->id}/events";
        $this->actingAs($this->admin(), 'sanctum');

        $this->postJson($path, [
            'team_id' => $fixture['home']->id,
            'scorer_id' => $extraHomePlayer->id,
        ])->assertUnprocessable();

        $this->postJson($path, [
            'team_id' => $fixture['away']->id,
            'scorer_id' => $fixture['homeScorer']->id,
        ])->assertUnprocessable();

        $this->postJson($path, [
            'team_id' => $fixture['home']->id,
            'scorer_id' => $fixture['homeScorer']->id,
            'assist_id' => $fixture['homeScorer']->id,
        ])->assertUnprocessable();

        $this->postJson($path, [
            'team_id' => $fixture['home']->id,
            'scorer_id' => $fixture['homeScorer']->id,
            'assist_id' => $extraHomePlayer->id,
        ])->assertUnprocessable();
    }

    public function test_finished_and_cancelled_matches_reject_participation_and_event_changes(): void
    {
        $fixture = $this->makeMatchFixture();
        $match = $fixture['match'];
        $event = ChampionshipMatchEvent::create([
            'match_id' => $match->id,
            'scorer_id' => $fixture['homeScorer']->id,
            'team_id' => $fixture['home']->id,
            'own_goal' => false,
        ]);
        $this->actingAs($this->admin(), 'sanctum');

        foreach (['cancelled', 'finished'] as $status) {
            $match->update(['status' => $status, 'home_score' => 1, 'away_score' => 0]);
            $this->postJson("/api/admin/championship-matches/{$match->id}/participation", [
                'championship_player_ids' => [$fixture['homeScorer']->id],
            ])->assertUnprocessable();
            $this->putJson("/api/admin/championship-events/{$event->id}", ['own_goal' => true])->assertUnprocessable();
            $this->deleteJson("/api/admin/championship-events/{$event->id}")->assertUnprocessable();
        }
    }

    public function test_participation_rejects_duplicate_ids_without_losing_existing_rows(): void
    {
        $fixture = $this->makeMatchFixture();
        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/admin/championship-matches/{$fixture['match']->id}/participation", [
            'championship_player_ids' => [$fixture['homeScorer']->id, $fixture['homeScorer']->id],
            'mvp_id' => $fixture['homeScorer']->id,
        ])->assertUnprocessable();

        $this->assertSame(4, $fixture['match']->participations()->count());
        $this->assertDatabaseHas('championship_match_participations', [
            'match_id' => $fixture['match']->id,
            'championship_player_id' => $fixture['homeScorer']->id,
        ]);
    }

    public function test_knockout_draw_requires_a_decisive_penalty_score(): void
    {
        $fixture = $this->makeMatchFixture('semi_final');
        $path = "/api/admin/championship-matches/{$fixture['match']->id}";
        $this->actingAs($this->admin(), 'sanctum');

        $this->putJson($path, [
            'status' => 'finished',
            'home_score' => 1,
            'away_score' => 1,
            'home_penalty_score' => 3,
            'away_penalty_score' => 3,
        ])->assertUnprocessable();

        $this->putJson($path, [
            'status' => 'finished',
            'home_score' => 1,
            'away_score' => 1,
            'home_penalty_score' => 4,
            'away_penalty_score' => 3,
        ])->assertOk();
        $this->assertSame($fixture['home']->id, $fixture['match']->fresh()->qualified_team_id);
    }

    public function test_playoff_advancement_creates_challenger_final_and_closes_on_grand_final(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        $this->completeGroupMatchesInTeamOrder($championship, $teams);
        app(ChampionshipPlayoffService::class)->generate($championship->fresh());
        $this->actingAs($this->admin(), 'sanctum');

        foreach ($championship->matches()->where('phase', 'semi_final')->orderBy('id')->get() as $semiFinal) {
            $this->putJson("/api/admin/championship-matches/{$semiFinal->id}", [
                'status' => 'finished', 'home_score' => 2, 'away_score' => 0,
            ])->assertOk();
        }

        $challenger = $championship->matches()->where('phase', 'challenger_final')->firstOrFail();
        $this->putJson("/api/admin/championship-matches/{$challenger->id}", [
            'status' => 'finished', 'home_score' => 1, 'away_score' => 0,
        ])->assertOk();

        $final = $championship->matches()->where('phase', 'final')->firstOrFail();
        $this->putJson("/api/admin/championship-matches/{$final->id}", [
            'status' => 'finished', 'home_score' => '1', 'away_score' => 1,
            'home_penalty_score' => '4', 'away_penalty_score' => 3,
        ])->assertOk();

        $this->assertSame('finished', $championship->fresh()->status);
        $this->assertSame($final->home_team_id, $final->fresh()->qualified_team_id);
        $this->assertSame(14, $championship->matches()->count());
    }

    public function test_public_reads_expose_championship_teams_and_rankings(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        $roster = $this->createCompleteRoster($championship, $teams);
        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/admin/championships/{$championship->id}/assignments", ['assignments' => $roster['assignments']])->assertOk();
        $this->postJson("/api/admin/championships/{$championship->id}/start")->assertOk();
        app(ChampionshipFixtureService::class)->generate($championship->fresh(), app(ChampionshipValidationService::class));
        app(ChampionshipAwardService::class)->seed($championship);

        $this->getJson("/api/championships/{$championship->id}")
            ->assertOk()
            ->assertJsonCount(5, 'championship.teams')
            ->assertJsonCount(10, 'matches');
        $this->getJson("/api/championships/{$championship->id}/awards")->assertOk()->assertJsonCount(13);
        $this->getJson("/api/championships/{$championship->id}/statistics?phase=group")->assertOk()->assertJsonCount(30);
        $this->getJson("/api/championships/{$championship->id}/rankings?phase=all")
            ->assertOk()
            ->assertJsonStructure(['scorers', 'assists', 'mvp', 'goalkeepers']);
    }

    public function test_voting_enforces_enrollment_self_vote_category_and_single_vote_rules(): void
    {
        [$championship] = $this->makeChampionship();
        app(ChampionshipAwardService::class)->seed($championship);
        $voter = $this->createFieldParticipant($championship, 1, '1.1');
        $candidate = $this->createFieldParticipant($championship, 1, '1.2');
        $notEnrolled = Player::factory()->linha()->create();
        $voterUser = User::factory()->create(['player_id' => $voter->player_id]);
        $unlinkedUser = User::factory()->create();
        $notEnrolledUser = User::factory()->create(['player_id' => $notEnrolled->id]);
        $category = $championship->awardCategories()->where('name', 'Bola de Ouro')->firstOrFail();

        $this->postJson("/api/championship-awards/{$category->id}/votes", ['candidate_id' => $candidate->id])->assertUnauthorized();
        $this->actingAs($unlinkedUser, 'sanctum')->postJson("/api/championship-awards/{$category->id}/votes", ['candidate_id' => $candidate->id])->assertForbidden();
        $this->actingAs($notEnrolledUser, 'sanctum')->postJson("/api/championship-awards/{$category->id}/votes", ['candidate_id' => $candidate->id])->assertForbidden();

        $this->actingAs($voterUser, 'sanctum')->postJson("/api/championship-awards/{$category->id}/votes", ['candidate_id' => $voter->id])->assertUnprocessable();
        $this->postJson("/api/championship-awards/{$category->id}/votes", ['candidate_id' => $candidate->id])->assertCreated();
        $this->postJson("/api/championship-awards/{$category->id}/votes", ['candidate_id' => $candidate->id])->assertUnprocessable();

        $category->update(['voting_open' => false]);
        $otherCandidate = $this->createFieldParticipant($championship, 2, '2.1');
        $otherUser = User::factory()->create(['player_id' => $otherCandidate->player_id]);
        $this->actingAs($otherUser, 'sanctum')->postJson("/api/championship-awards/{$category->id}/votes", ['candidate_id' => $candidate->id])->assertUnprocessable();
    }

    public function test_goalkeeper_and_xerifao_awards_enforce_candidate_eligibility(): void
    {
        [$championship, $teams] = $this->makeChampionship();
        app(ChampionshipAwardService::class)->seed($championship);
        $voter = $this->createFieldParticipant($championship, 1, '1.1');
        $fieldCandidate = $this->createFieldParticipant($championship, 2, '2.1', 'atacante');
        $defender = $this->createFieldParticipant($championship, 3, '3.1', 'zagueiro');
        $goalkeeper = $this->createGoalkeeperParticipant($championship, 1);
        $teams[0]->players()->attach($goalkeeper->id);
        $user = User::factory()->create(['player_id' => $voter->player_id]);
        $this->actingAs($user, 'sanctum');

        $wallCategory = $championship->awardCategories()->where('name', 'Paredão do Campeonato')->firstOrFail();
        $sheriffCategory = $championship->awardCategories()->where('name', 'Xerifão do Campeonato')->firstOrFail();

        $this->postJson("/api/championship-awards/{$wallCategory->id}/votes", ['candidate_id' => $fieldCandidate->id])->assertUnprocessable();
        $this->postJson("/api/championship-awards/{$wallCategory->id}/votes", ['candidate_id' => $goalkeeper->id])->assertCreated();
        $this->postJson("/api/championship-awards/{$sheriffCategory->id}/votes", ['candidate_id' => $fieldCandidate->id])->assertUnprocessable();
        $this->postJson("/api/championship-awards/{$sheriffCategory->id}/votes", ['candidate_id' => $defender->id])->assertCreated();
    }

    public function test_award_voting_closes_globally_and_published_results_are_consolidated(): void
    {
        [$championship] = $this->makeChampionship();
        app(ChampionshipAwardService::class)->seed($championship);
        $voterA = $this->createFieldParticipant($championship, 1, '1.1');
        $voterB = $this->createFieldParticipant($championship, 1, '1.2');
        $winner = $this->createFieldParticipant($championship, 2, '2.1');
        $userA = User::factory()->create(['player_id' => $voterA->player_id]);
        $userB = User::factory()->create(['player_id' => $voterB->player_id]);
        $category = $championship->awardCategories()->where('name', 'Bola de Ouro')->firstOrFail();

        $this->actingAs($userA, 'sanctum')->postJson("/api/championship-awards/{$category->id}/votes", ['candidate_id' => $winner->id])->assertCreated();
        $this->actingAs($userB, 'sanctum')->postJson("/api/championship-awards/{$category->id}/votes", ['candidate_id' => $winner->id])->assertCreated();

        $championship->update(['status' => 'finished', 'phase' => 'finished']);
        $this->actingAs($this->admin(), 'sanctum')->putJson("/api/admin/championships/{$championship->id}/awards/voting-state")->assertOk();
        $this->assertSame(0, $championship->awardCategories()->where('type', 'vote')->where('voting_open', true)->count());

        $published = $this->postJson("/api/admin/championships/{$championship->id}/awards/publish")->assertOk();
        $this->assertSame(100.0, (float) $category->fresh()->results()->where('winner_id', $winner->id)->value('percentage'));
        $this->assertTrue((bool) $category->fresh()->published);
        $this->assertCount(15, $published->json());
    }

    public function test_simple_voter_registration_session_status_and_unique_category_votes(): void
    {
        [$championship] = $this->makeChampionship();
        app(ChampionshipAwardService::class)->seed($championship);
        $voterParticipant = $this->createFieldParticipant($championship, 1, '1.1');
        $candidate = $this->createFieldParticipant($championship, 2, '2.1');
        $category = $championship->awardCategories()->where('name', 'Bola de Ouro')->firstOrFail();
        $otherCategory = $championship->awardCategories()->where('name', 'Revelação do Campeonato')->firstOrFail();

        $this->getJson("/api/championships/{$championship->id}/voting/players")
            ->assertOk()
            ->assertJsonFragment(['id' => $voterParticipant->id, 'name' => $voterParticipant->player->name]);

        $registration = $this->postJson("/api/championships/{$championship->id}/voting/voters", [
            'championship_player_id' => $voterParticipant->id,
            'pin' => '123456',
            'pin_confirmation' => '123456',
        ])->assertCreated();
        $token = $registration->json('token');
        $voter = ChampionshipVoter::where('championship_player_id', $voterParticipant->id)->firstOrFail();
        $this->assertNotSame('123456', $voter->pin_hash);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('123456', $voter->pin_hash));

        $this->postJson("/api/championships/{$championship->id}/voting/voters", [
            'championship_player_id' => $voterParticipant->id,
            'pin' => '234567',
            'pin_confirmation' => '234567',
        ])->assertStatus(409);

        $this->postJson("/api/championship-votes/{$category->id}", ['candidate_id' => $candidate->id])
            ->assertUnauthorized();
        $this->withHeader('X-Championship-Voter-Session', $token)
            ->postJson("/api/championship-votes/{$category->id}", ['candidate_id' => $candidate->id])
            ->assertCreated();
        $this->assertDatabaseHas('championship_votes', [
            'championship_award_category_id' => $category->id,
            'championship_voter_id' => $voter->id,
            'voter_championship_player_id' => $voterParticipant->id,
            'voter_id' => null,
        ]);
        $this->withHeader('X-Championship-Voter-Session', $token)
            ->postJson("/api/championship-votes/{$category->id}", ['candidate_id' => $candidate->id])
            ->assertUnprocessable();
        $this->withHeader('X-Championship-Voter-Session', $token)
            ->postJson("/api/championship-votes/{$otherCategory->id}", ['candidate_id' => $candidate->id])
            ->assertCreated();

        $status = $this->withHeader('X-Championship-Voter-Session', $token)
            ->getJson("/api/championships/{$championship->id}/voting/session")
            ->assertOk();
        $this->assertTrue(collect($status->json('categories'))->firstWhere('id', $category->id)['has_voted']);
        $this->assertTrue(collect($status->json('categories'))->firstWhere('id', $otherCategory->id)['has_voted']);
    }

    public function test_simple_voter_pin_attempts_lock_for_fifteen_minutes_and_then_reset(): void
    {
        [$championship] = $this->makeChampionship();
        $participant = $this->createFieldParticipant($championship, 1, '1.1');
        $this->postJson("/api/championships/{$championship->id}/voting/voters", [
            'championship_player_id' => $participant->id,
            'pin' => '654321',
            'pin_confirmation' => '654321',
        ])->assertCreated();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson("/api/championships/{$championship->id}/voting/session", [
                'championship_player_id' => $participant->id,
                'pin' => '000000',
            ])->assertUnprocessable();
        }

        $this->postJson("/api/championships/{$championship->id}/voting/session", [
            'championship_player_id' => $participant->id,
            'pin' => '000000',
        ])->assertStatus(429);
        $this->postJson("/api/championships/{$championship->id}/voting/session", [
            'championship_player_id' => $participant->id,
            'pin' => '654321',
        ])->assertStatus(429);

        $this->travel(16)->minutes();
        $this->postJson("/api/championships/{$championship->id}/voting/session", [
            'championship_player_id' => $participant->id,
            'pin' => '654321',
        ])->assertOk()->assertJsonPath('voter.championship_player_id', $participant->id);
    }

    public function test_admin_can_relink_and_reset_voter_without_changing_existing_votes(): void
    {
        [$championship] = $this->makeChampionship();
        app(ChampionshipAwardService::class)->seed($championship);
        $first = $this->createFieldParticipant($championship, 1, '1.1');
        $second = $this->createFieldParticipant($championship, 1, '1.2');
        $third = $this->createFieldParticipant($championship, 1, '1.3');
        $candidate = $this->createFieldParticipant($championship, 2, '2.1');
        $category = $championship->awardCategories()->where('name', 'Bola de Ouro')->firstOrFail();

        $registration = $this->postJson("/api/championships/{$championship->id}/voting/voters", [
            'championship_player_id' => $first->id,
            'pin' => '111111',
            'pin_confirmation' => '111111',
        ])->assertCreated();
        $token = $registration->json('token');
        $voter = ChampionshipVoter::where('championship_player_id', $first->id)->firstOrFail();
        $this->withHeader('X-Championship-Voter-Session', $token)
            ->postJson("/api/championship-votes/{$category->id}", ['candidate_id' => $candidate->id])
            ->assertCreated();

        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/championships/{$championship->id}/voters")
            ->assertOk()
            ->assertJsonFragment(['name' => $category->name]);
        $this->putJson("/api/admin/championship-voters/{$voter->id}/player", [
            'championship_player_id' => $second->id,
        ])->assertOk()->assertJsonPath('championship_player_id', $second->id);

        $vote = ChampionshipVote::where('championship_voter_id', $voter->id)->firstOrFail();
        $this->assertSame($first->id, $vote->voter_championship_player_id);
        $this->assertSame($candidate->id, $vote->candidate_id);
        $this->withHeader('X-Championship-Voter-Session', $token)
            ->getJson("/api/championships/{$championship->id}/voting/session")
            ->assertUnauthorized();

        $this->postJson("/api/championships/{$championship->id}/voting/session", [
            'championship_player_id' => $second->id,
            'pin' => '111111',
        ])->assertOk();
        $newVoterRegistration = $this->postJson("/api/championships/{$championship->id}/voting/voters", [
            'championship_player_id' => $third->id,
            'pin' => '222222',
            'pin_confirmation' => '222222',
        ])->assertCreated();
        $secondVoterId = ChampionshipVoter::where('championship_player_id', $third->id)->value('id');

        $this->putJson("/api/admin/championship-voters/{$secondVoterId}/player", [
            'championship_player_id' => $second->id,
        ])->assertStatus(409);
        $this->putJson("/api/admin/championship-voters/{$voter->id}/pin", [
            'pin' => '333333',
            'pin_confirmation' => '333333',
        ])->assertOk();
        $this->postJson("/api/championships/{$championship->id}/voting/session", [
            'championship_player_id' => $second->id,
            'pin' => '111111',
        ])->assertUnprocessable();
        $this->postJson("/api/championships/{$championship->id}/voting/session", [
            'championship_player_id' => $second->id,
            'pin' => '333333',
        ])->assertOk();

        $audit = $championship->auditLogs()->where('action', 'voter.player.relinked')->latest()->firstOrFail();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame($first->id, $audit->payload['previous_championship_player_id']);
        $this->assertSame($second->id, $audit->payload['new_championship_player_id']);
        $this->assertDatabaseHas('championship_votes', ['id' => $vote->id, 'candidate_id' => $candidate->id]);
        $this->assertSame($voter->id, $vote->fresh()->championship_voter_id);
        $this->assertNotEmpty($newVoterRegistration->json('token'));
    }

    public function test_voter_administration_is_admin_only_and_rejects_players_from_another_championship(): void
    {
        [$championship] = $this->makeChampionship();
        [$otherChampionship] = $this->makeChampionship('Outro campeonato');
        $participant = $this->createFieldParticipant($championship, 1, '1.1');
        $otherParticipant = $this->createFieldParticipant($otherChampionship, 1, '1.1');
        $registration = $this->postJson("/api/championships/{$championship->id}/voting/voters", [
            'championship_player_id' => $participant->id,
            'pin' => '444444',
            'pin_confirmation' => '444444',
        ])->assertCreated();
        $voterId = ChampionshipVoter::where('championship_player_id', $participant->id)->value('id');

        $this->getJson("/api/admin/championships/{$championship->id}/voters")->assertUnauthorized();
        $nonAdmin = (new User)->forceFill(['id' => 99998, 'profile' => 'common']);
        $this->actingAs($nonAdmin, 'sanctum')
            ->getJson("/api/admin/championships/{$championship->id}/voters")
            ->assertForbidden();
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/championship-voters/{$voterId}/player", ['championship_player_id' => $otherParticipant->id])
            ->assertUnprocessable();
        $this->assertDatabaseHas('championship_voters', [
            'id' => $voterId,
            'championship_player_id' => $participant->id,
        ]);
        $this->assertNotEmpty($registration->json('token'));
    }

    /** @return array{0: Championship, 1: Collection<int, ChampionshipTeam>} */
    private function makeChampionship(string $name = 'Teste'): array
    {
        $championship = Championship::create(['name' => $name]);
        $teams = collect(range(1, 5))->map(fn (int $number) => $championship->teams()->create(['name' => "Equipe {$number}"]));

        return [$championship, $teams];
    }

    private function admin(): User
    {
        return User::factory()->create(['profile' => 'admin']);
    }

    private function playerRegistrationPath(Championship $championship): string
    {
        return "/api/admin/championships/{$championship->id}/players";
    }

    private function createFieldParticipant(Championship $championship, int $group, string $level, string $sportPosition = 'atacante'): ChampionshipPlayer
    {
        $player = Player::factory()->linha()->create();

        return $championship->players()->create([
            'player_id' => $player->id,
            'player_type' => 'field',
            'stone_group' => $group,
            'stone_level' => $level,
            'sport_position' => $sportPosition,
        ]);
    }

    private function createGoalkeeperParticipant(Championship $championship, int $group): ChampionshipPlayer
    {
        $player = Player::factory()->goleiro()->create();

        return $championship->players()->create([
            'player_id' => $player->id,
            'player_type' => 'goalkeeper',
            'stone_group' => $group,
        ]);
    }

    /** @return array{assignments: array<int, array{team_id: int, championship_player_ids: array<int, int>}>, goalkeepers: array<int, ChampionshipPlayer>} */
    private function createCompleteRoster(Championship $championship, Collection $teams): array
    {
        $assignments = [];
        $goalkeepers = [];

        foreach ($teams->values() as $teamIndex => $team) {
            $fieldIds = [];
            for ($group = 1; $group <= 5; $group++) {
                $level = sprintf('%d.%d', $group, $teamIndex + 1);
                $participant = $this->createFieldParticipant($championship, $group, $level);
                $fieldIds[] = $participant->id;
            }

            $goalkeeper = $this->createGoalkeeperParticipant($championship, $teamIndex + 1);
            $team->players()->attach($goalkeeper->id);
            $goalkeepers[] = $goalkeeper;
            $assignments[] = ['team_id' => $team->id, 'championship_player_ids' => $fieldIds];
        }

        return ['assignments' => $assignments, 'goalkeepers' => $goalkeepers];
    }

    private function completeGroupMatchesInTeamOrder(Championship $championship, Collection $teams): void
    {
        $championship->update(['status' => 'active', 'phase' => 'group']);
        app(ChampionshipFixtureService::class)->generate($championship, app(ChampionshipValidationService::class));
        $positions = $teams->pluck('id')->flip();

        foreach ($championship->matches()->where('phase', 'group')->get() as $match) {
            $homeWins = $positions[$match->home_team_id] < $positions[$match->away_team_id];
            $match->update([
                'status' => 'finished',
                'home_score' => $homeWins ? 2 : 0,
                'away_score' => $homeWins ? 0 : 2,
            ]);
        }
    }

    /** @return array{championship: Championship, home: ChampionshipTeam, away: ChampionshipTeam, match: ChampionshipMatch, homeScorer: ChampionshipPlayer, homeAssist: ChampionshipPlayer, awayScorer: ChampionshipPlayer} */
    private function makeMatchFixture(string $phase = 'group'): array
    {
        [$championship, $teams] = $this->makeChampionship();
        $championship->update(['status' => 'active', 'phase' => $phase === 'group' ? 'group' : 'playoffs']);
        $homeScorer = $this->createFieldParticipant($championship, 1, '1.1');
        $homeAssist = $this->createFieldParticipant($championship, 2, '2.1');
        $awayScorer = $this->createFieldParticipant($championship, 1, '1.2');
        $awayTeammate = $this->createFieldParticipant($championship, 2, '2.2');
        $teams[0]->players()->attach([$homeScorer->id, $homeAssist->id]);
        $teams[1]->players()->attach([$awayScorer->id, $awayTeammate->id]);
        $match = ChampionshipMatch::create([
            'championship_id' => $championship->id,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'phase' => $phase,
        ]);
        foreach ([$homeScorer, $homeAssist, $awayScorer, $awayTeammate] as $participant) {
            $match->participations()->create(['championship_player_id' => $participant->id, 'mvp' => false]);
        }

        return [
            'championship' => $championship,
            'home' => $teams[0],
            'away' => $teams[1],
            'match' => $match,
            'homeScorer' => $homeScorer,
            'homeAssist' => $homeAssist,
            'awayScorer' => $awayScorer,
        ];
    }
}
