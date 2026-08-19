<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientFieldPlayersException;
use App\Exceptions\InsufficientGoalkeepersException;
use App\Models\GoalPosition;
use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use App\Services\TeamOrganizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamOrganizerGoalkeeperTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TeamOrganizerService
    {
        return app(TeamOrganizerService::class);
    }

    public function test_automatic_organize_attaches_one_fixed_goalkeeper_per_team_when_counts_match(): void
    {
        $pelada = Pelada::factory()->create(['qtd_times' => 2, 'qtd_jogadores_por_time' => 5, 'qtd_goleiros' => 2]);
        $fieldPlayers = Player::factory()->count(10)->linha()->create();
        $goalkeepers = Player::factory()->count(2)->goleiro()->create();

        $teams = $this->service()->organizeAutomatic($pelada, $fieldPlayers->pluck('id')->merge($goalkeepers->pluck('id'))->all());

        $this->assertSame(0, GoalPosition::where('pelada_id', $pelada->id)->count());
        $this->assertSame(2, Team::where('pelada_id', $pelada->id)->count());

        $goalkeeperIdsInTeams = $teams->flatMap(fn (Team $team) => $team->players->where('position', 'goleiro')->pluck('id'));
        $this->assertEqualsCanonicalizing($goalkeepers->pluck('id')->all(), $goalkeeperIdsInTeams->all());
    }

    public function test_automatic_organize_creates_independent_goal_positions_when_there_are_fewer_goalkeepers_than_teams(): void
    {
        $pelada = Pelada::factory()->create(['qtd_times' => 3, 'qtd_jogadores_por_time' => 5, 'qtd_goleiros' => 2]);
        $fieldPlayers = Player::factory()->count(15)->linha()->create();
        $goalkeepers = Player::factory()->count(2)->goleiro()->create();

        $teams = $this->service()->organizeAutomatic($pelada, $fieldPlayers->pluck('id')->merge($goalkeepers->pluck('id'))->all());

        $this->assertSame(3, $teams->count());
        foreach ($teams as $team) {
            $this->assertSame(0, $team->players->where('position', 'goleiro')->count());
        }

        $goalPositions = GoalPosition::where('pelada_id', $pelada->id)->orderBy('numero')->get();
        $this->assertSame(2, $goalPositions->count());
        $this->assertEqualsCanonicalizing($goalkeepers->pluck('id')->all(), $goalPositions->pluck('goleiro_id')->all());
        $this->assertSame([1, 2], $goalPositions->pluck('numero')->all());
    }

    public function test_automatic_organize_leaves_surplus_goalkeepers_unassigned_when_there_are_more_goalkeepers_than_teams(): void
    {
        $pelada = Pelada::factory()->create(['qtd_times' => 2, 'qtd_jogadores_por_time' => 5, 'qtd_goleiros' => 2]);
        $fieldPlayers = Player::factory()->count(10)->linha()->create();
        $goalkeepers = Player::factory()->count(3)->goleiro()->create();

        $teams = $this->service()->organizeAutomatic($pelada, $fieldPlayers->pluck('id')->merge($goalkeepers->pluck('id'))->all());

        $this->assertSame(0, GoalPosition::where('pelada_id', $pelada->id)->count());

        $goalkeeperIdsInTeams = $teams->flatMap(fn (Team $team) => $team->players->where('position', 'goleiro')->pluck('id'));
        $this->assertCount(2, $goalkeeperIdsInTeams);
        $this->assertTrue($goalkeepers->pluck('id')->contains($goalkeeperIdsInTeams->first()));
    }

    public function test_automatic_organize_throws_when_there_are_not_enough_goalkeepers(): void
    {
        $pelada = Pelada::factory()->create(['qtd_times' => 2, 'qtd_jogadores_por_time' => 5, 'qtd_goleiros' => 2]);
        $fieldPlayers = Player::factory()->count(10)->linha()->create();
        $goalkeeper = Player::factory()->goleiro()->create();

        $this->expectException(InsufficientGoalkeepersException::class);

        $this->service()->organizeAutomatic($pelada, $fieldPlayers->pluck('id')->push($goalkeeper->id)->all());
    }

    public function test_automatic_organize_throws_when_there_are_not_enough_field_players(): void
    {
        $pelada = Pelada::factory()->create(['qtd_times' => 2, 'qtd_jogadores_por_time' => 5, 'qtd_goleiros' => 2]);
        $fieldPlayers = Player::factory()->count(4)->linha()->create();
        $goalkeepers = Player::factory()->count(2)->goleiro()->create();

        $this->expectException(InsufficientFieldPlayersException::class);

        $this->service()->organizeAutomatic($pelada, $fieldPlayers->pluck('id')->merge($goalkeepers->pluck('id'))->all());
    }

    public function test_manual_organize_creates_goal_positions_instead_of_attaching_goalkeeper_to_a_team(): void
    {
        $pelada = Pelada::factory()->create(['qtd_times' => 3, 'qtd_jogadores_por_time' => 2, 'qtd_goleiros' => 2]);
        $teamOneLine = Player::factory()->count(2)->linha()->create();
        $teamTwoLine = Player::factory()->count(2)->linha()->create();
        $teamThreeLine = Player::factory()->count(2)->linha()->create();
        $goalkeepers = Player::factory()->count(2)->goleiro()->create();

        $teamAssignments = [
            ['team_number' => 1, 'player_ids' => $teamOneLine->pluck('id')->all()],
            ['team_number' => 2, 'player_ids' => $teamTwoLine->pluck('id')->all()],
            ['team_number' => 3, 'player_ids' => $teamThreeLine->pluck('id')->all()],
        ];
        $goalkeeperAssignments = [
            ['position' => 1, 'player_id' => $goalkeepers[0]->id],
            ['position' => 2, 'player_id' => $goalkeepers[1]->id],
        ];

        $teams = $this->service()->organizeManual($pelada, $teamAssignments, $goalkeeperAssignments);

        foreach ($teams as $team) {
            $this->assertSame(0, $team->players->where('position', 'goleiro')->count());
        }

        $goalPositions = GoalPosition::where('pelada_id', $pelada->id)->orderBy('numero')->get();
        $this->assertSame([$goalkeepers[0]->id, $goalkeepers[1]->id], $goalPositions->pluck('goleiro_id')->all());
    }

    public function test_reorganizing_a_pelada_clears_previous_teams_and_goal_positions(): void
    {
        $pelada = Pelada::factory()->create(['qtd_times' => 3, 'qtd_jogadores_por_time' => 5, 'qtd_goleiros' => 2]);
        $fieldPlayers = Player::factory()->count(15)->linha()->create();
        $goalkeepers = Player::factory()->count(2)->goleiro()->create();

        $this->service()->organizeAutomatic($pelada, $fieldPlayers->pluck('id')->merge($goalkeepers->pluck('id'))->all());
        $this->assertSame(2, GoalPosition::where('pelada_id', $pelada->id)->count());

        $newFieldPlayers = Player::factory()->count(15)->linha()->create();
        $newGoalkeepers = Player::factory()->count(2)->goleiro()->create();
        $this->service()->organizeAutomatic($pelada, $newFieldPlayers->pluck('id')->merge($newGoalkeepers->pluck('id'))->all());

        $goalPositions = GoalPosition::where('pelada_id', $pelada->id)->orderBy('numero')->get();
        $this->assertSame(2, $goalPositions->count());
        $this->assertEqualsCanonicalizing($newGoalkeepers->pluck('id')->all(), $goalPositions->pluck('goleiro_id')->all());
    }
}
