<?php
namespace Tests\Feature;
use App\Models\Championship;
use App\Models\ChampionshipMatch;
use App\Models\Player;
use App\Services\ChampionshipFixtureService;
use App\Services\ChampionshipOddsService;
use App\Services\ChampionshipStandingsService;
use App\Services\ChampionshipAwardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ChampionshipModuleTest extends TestCase
{
    use RefreshDatabase;
    public function test_generates_ten_round_robin_matches(): void
    {
        $championship=Championship::create(['name'=>'Teste']);
        foreach(['A','B','C','D','E'] as $name)$championship->teams()->create(['name'=>$name]);
        app(ChampionshipFixtureService::class)->generate($championship);
        $this->assertCount(10,$championship->matches()->get());
        $this->assertCount(10,$championship->matches()->select('home_team_id','away_team_id')->get()->unique(fn($m)=>min($m->home_team_id,$m->away_team_id).'-'.max($m->home_team_id,$m->away_team_id)));
    }
    public function test_standings_apply_three_points_for_a_win(): void
    {
        $championship=Championship::create(['name'=>'Teste']); $teams=collect(['A','B','C','D','E'])->map(fn($name)=>$championship->teams()->create(['name'=>$name]));
        $match=ChampionshipMatch::create(['championship_id'=>$championship->id,'home_team_id'=>$teams[0]->id,'away_team_id'=>$teams[1]->id,'phase'=>'group','status'=>'finished','home_score'=>3,'away_score'=>1]);
        $table=app(ChampionshipStandingsService::class)->calculate($championship); $this->assertSame(3,$table[0]['points']); $this->assertSame($teams[0]->id,$table[0]['team_id']);
    }
    public function test_odds_are_persisted_and_sum_to_one(): void
    {
        $championship=Championship::create(['name'=>'Teste']); $teams=collect(['A','B','C','D','E'])->map(fn($name)=>$championship->teams()->create(['name'=>$name]));
        foreach($teams->take(2) as $teamIndex=>$team){ for($i=1;$i<=5;$i++){ $player=Player::factory()->create(['position'=>'linha']); $participant=$championship->players()->create(['player_id'=>$player->id,'player_type'=>'field','stone_group'=>$i,'stone_level'=>number_format($i+$teamIndex*0.1,1)]); $team->players()->attach($participant->id); } $keeper=Player::factory()->create(['position'=>'goleiro']); $goalkeeper=$championship->players()->create(['player_id'=>$keeper->id,'player_type'=>'goalkeeper','stone_group'=>1]); $team->players()->attach($goalkeeper->id); }
        $match=ChampionshipMatch::create(['championship_id'=>$championship->id,'home_team_id'=>$teams[0]->id,'away_team_id'=>$teams[1]->id,'phase'=>'group']); $odds=app(ChampionshipOddsService::class)->calculate($match); $this->assertNotNull($odds->id); $this->assertEqualsWithDelta(1,(float)$odds->home_probability+(float)$odds->draw_probability+(float)$odds->away_probability,0.01);
    }
    public function test_award_voting_starts_open_and_ends_for_all_categories(): void
    {
        $championship=Championship::create(['name'=>'Teste']); app(ChampionshipAwardService::class)->seed($championship);
        $this->assertTrue((bool)$championship->awardCategories()->where('type','vote')->firstOrFail()->voting_open);
        $closed=app(ChampionshipAwardService::class)->endVoting($championship);
        $this->assertGreaterThan(0,$closed); $this->assertSame(0,$championship->awardCategories()->where('type','vote')->where('voting_open',true)->count());
    }
}
