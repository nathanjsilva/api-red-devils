<?php
namespace App\Http\Controllers;
use App\Models\Championship;
use App\Models\ChampionshipMatch;
use App\Models\ChampionshipPlayer;
use App\Models\ChampionshipAwardCategory;
use App\Models\ChampionshipVote;
use App\Models\ChampionshipAwardResult;
use App\Models\ChampionshipMatchEvent;
use App\Models\ChampionshipTieBreaker;
use App\Models\ChampionshipTeam;
use App\Models\Player;
use App\Models\User;
use App\Models\ChampionshipMatchParticipation;
use App\Services\ChampionshipFixtureService;
use App\Services\ChampionshipOddsService;
use App\Services\ChampionshipStandingsService;
use App\Services\ChampionshipPlayoffService;
use App\Services\ChampionshipAwardService;
use App\Services\GoalkeeperDrawService;
use App\Services\ChampionshipAuditService;
use App\Services\ChampionshipValidationService;
use App\Services\ChampionshipStatisticsService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChampionshipController extends Controller
{
    public function index() { return Championship::withCount(['teams','players','matches'])->latest()->get(); }
    public function show(Championship $championship, ChampionshipStandingsService $standings)
    {
        $matches=$championship->matches()->with(['homeTeam.players.player','awayTeam.players.player','odds'])->orderBy('phase')->orderBy('round')->get(); $final=$matches->first(fn($m)=>$m->phase==='final' && $m->status==='finished');
        $table = $standings->calculate($championship);
        $champion = $final?->qualified_team_id ? $championship->teams()->with('players.player')->find($final->qualified_team_id) : null;
        if ($champion) { $champion->campaign = collect($table)->firstWhere('team_id', $champion->id); $champion->decisive_matches = $matches->whereIn('phase', ['semi_final', 'challenger_final', 'final'])->where('status', 'finished')->values(); }
        return response()->json(['championship'=>$championship->load(['teams.players.player','players.player','goalkeeperDraws']), 'standings'=>$table, 'matches'=>$matches, 'champion'=>$champion]);
    }
    public function store(Request $request)
    {
        $data = $request->validate(['name'=>'required|string|max:100','goalkeeper_rule_enabled'=>'boolean']);
        return DB::transaction(function () use ($data) {
            $championship = Championship::create($data);
            foreach (['Surubeiros FC','Hardworkers FC','Borracha Fraca FC','Fimose FC','Transformers FC'] as $name) $championship->teams()->create(['name'=>$name]);
            app(ChampionshipAwardService::class)->seed($championship);
            return $championship->load('teams');
        });
    }
    public function registerPlayer(Request $request, Championship $championship)
    {
        abort_if($championship->status !== 'draft', 422, 'As inscrições só podem ser alteradas antes do início.');
        $data = $request->validate(['player_id'=>'required|exists:players,id','player_type'=>'required|in:field,goalkeeper','stone_group'=>'required|integer|min:1|max:5','stone_level'=>'required_if:player_type,field|nullable|numeric|between:1.1,5.5','sport_position'=>'nullable|string|max:30']);
        $globalPlayer=Player::findOrFail($data['player_id']); abort_if(($data['player_type']==='goalkeeper') !== ($globalPlayer->position==='goleiro'),422,'A posição do jogador não corresponde ao tipo da inscrição.');
        abort_if($championship->players()->where('player_id', $data['player_id'])->exists(), 422, 'Jogador já inscrito.');
        abort_if($data['player_type'] === 'field' && $championship->players()->where('player_type','field')->count() >= 25, 422, 'Limite de jogadores de linha atingido.');
        abort_if($data['player_type'] === 'goalkeeper' && $championship->players()->where('player_type','goalkeeper')->count() >= 5, 422, 'Limite de goleiros atingido.');
        if ($data['player_type'] === 'field') abort_if($championship->players()->where('stone_level',$data['stone_level'])->exists(), 422, 'Esta classificação já foi usada.');
        return $championship->players()->create($data)->load('player');
    }
    public function assignPlayers(Request $request, Championship $championship)
    {
        abort_if($championship->status !== 'draft', 422, 'A formação só pode ser alterada antes do início.');
        $data = $request->validate(['assignments'=>'required|array|size:5','assignments.*.team_id'=>'required|exists:championship_teams,id','assignments.*.championship_player_ids'=>'required|array|size:5','assignments.*.championship_player_ids.*'=>'exists:championship_players,id']);
        DB::transaction(function () use ($championship, $data) {
            $teamIds = collect($data['assignments'])->pluck('team_id');
            abort_if($teamIds->unique()->count() !== 5 || ChampionshipTeam::where('championship_id',$championship->id)->whereIn('id',$teamIds)->count() !== 5, 422, 'A formação deve usar exatamente os cinco times do campeonato.');
            $championship->teams()->each(fn ($team) => $team->players()->detach());
            $seen = collect();
            foreach ($data['assignments'] as $assignment) {
                $ids = collect($assignment['championship_player_ids']);
                abort_if(ChampionshipPlayer::where('championship_id', $championship->id)->whereIn('id', $ids)->count() !== $ids->count(), 422, 'Há participantes de outro campeonato na formação.');
                abort_if($ids->intersect($seen)->isNotEmpty(), 422, 'Um jogador não pode estar em duas equipes.');
                abort_if($ids->count() !== $ids->unique()->count(), 422, 'Jogadores duplicados na equipe.');
                abort_if(ChampionshipPlayer::whereIn('id',$ids)->where('player_type','!=','field')->exists(), 422, 'Somente jogadores de linha podem ocupar estas vagas.');
                $groups = ChampionshipPlayer::whereIn('id',$ids)->pluck('stone_group');
                abort_if($groups->count() !== $groups->unique()->count(), 422, 'Cada equipe precisa ter uma Pedra de cada grupo.');
                ChampionshipTeam::findOrFail($assignment['team_id'])->players()->attach($ids);
                $seen = $seen->merge($ids);
            }
        });
        return ['message'=>'Equipes formadas com sucesso.'];
    }
    public function generateFixtures(Championship $championship, ChampionshipFixtureService $service) { $service->generate($championship); return ['message'=>'Confrontos gerados.']; }
    public function start(Championship $championship, ChampionshipValidationService $validator, ChampionshipAuditService $audit) { $validator->validateReady($championship); $championship->update(['status'=>'active','phase'=>'group']); $audit->record($championship->id,'championship.started',$championship); return $championship->fresh(); }
    public function updateTeam(Request $request, ChampionshipTeam $team, ChampionshipAuditService $audit) { $data=$request->validate(['name'=>'required|string|max:100']); abort_if($team->championship->status!=='draft',422,'Times só podem ser alterados antes do início.'); $team->update($data); $audit->record($team->championship_id,'team.updated',$team,$data); return $team->fresh(); }
    public function resolveTie(Request $request, Championship $championship, ChampionshipAuditService $audit) { abort_if($championship->phase!=='group',422,'O desempate só pode ser resolvido na primeira fase.'); $data=$request->validate(['team_a_id'=>'required|exists:championship_teams,id','team_b_id'=>'required|exists:championship_teams,id','winner_id'=>'required|exists:championship_teams,id']); abort_if($data['team_a_id']===$data['team_b_id'] || !in_array($data['winner_id'],[$data['team_a_id'],$data['team_b_id']],true),422,'Equipes inválidas para o desempate.'); $tie=ChampionshipTieBreaker::updateOrCreate(['championship_id'=>$championship->id,'team_a_id'=>$data['team_a_id'],'team_b_id'=>$data['team_b_id']],['winner_id'=>$data['winner_id'],'resolved_by'=>$request->user()->id,'reason'=>'administrative_draw']); $audit->record($championship->id,'standings.tie_breaker.resolved',$tie,$data); return $tie; }
    public function users() { return User::with('player')->orderBy('name')->get(['id','name','username','profile','player_id']); }
    public function linkUserPlayer(Request $request, User $user) { $data=$request->validate(['player_id'=>'nullable|exists:players,id']); abort_if($data['player_id'] && User::where('player_id',$data['player_id'])->where('id','!=',$user->id)->exists(),422,'Este jogador já está vinculado a outro usuário.'); $user->update(['player_id'=>$data['player_id']??null]); return $user->fresh('player'); }
    public function generatePlayoffs(Championship $championship, ChampionshipPlayoffService $service) { $service->generate($championship); return ['message'=>'Semifinais geradas.']; }
    public function uploadCrest(Request $request, ChampionshipTeam $team, ChampionshipAuditService $audit)
    {
        $request->validate(['crest'=>'required|image|mimes:jpg,jpeg,png,webp|max:2048']);
        abort_if($team->championship->status === 'finished', 422, 'O campeonato já foi encerrado.');
        if ($team->crest_path) Storage::disk('public')->delete($team->crest_path);
        $team->update(['crest_path'=>$request->file('crest')->store('championship-crests','public')]); $audit->record($team->championship_id,'team.crest.updated',$team); return $team->fresh();
    }
    public function removeCrest(ChampionshipTeam $team, ChampionshipAuditService $audit) { if ($team->crest_path) Storage::disk('public')->delete($team->crest_path); $team->update(['crest_path'=>null]); $audit->record($team->championship_id,'team.crest.removed',$team); return $team->fresh(); }
    public function drawGoalkeepers(Request $request, Championship $championship, GoalkeeperDrawService $service, ChampionshipAuditService $audit) { $data=$request->validate(['confirmed'=>'boolean']); $service->draw($championship,$request->user()->id,(bool)($data['confirmed']??false)); $audit->record($championship->id,'goalkeepers.draw'); return $championship->goalkeeperDraws()->with(['team','championshipPlayer.player'])->latest('draw_number')->get(); }
    public function updateMatch(Request $request, ChampionshipMatch $match)
    {
        $data = $request->validate(['home_score'=>'required|integer|min:0','away_score'=>'required|integer|min:0','home_penalty_score'=>'nullable|integer|min:0','away_penalty_score'=>'nullable|integer|min:0','status'=>'required|in:finished,cancelled']);
        if ($match->phase !== 'group' && $data['home_score'] === $data['away_score']) abort_if(!isset($data['home_penalty_score'],$data['away_penalty_score']), 422, 'Eliminatórias empatadas exigem pênaltis.');
        $match->update($data);
        if ($data['status'] === 'finished') $this->advancePlayoff($match->fresh());
        return $match->fresh(['homeTeam','awayTeam']);
    }
    public function addEvent(Request $request, ChampionshipMatch $match)
    {
        abort_if($match->status === 'finished', 422, 'Partidas finalizadas não podem receber novos eventos.');
        $data = $request->validate(['scorer_id'=>'nullable|exists:championship_players,id','assist_id'=>'nullable|exists:championship_players,id','team_id'=>'required|exists:championship_teams,id','own_goal'=>'boolean','minute'=>'nullable|integer|min:0|max:150']);
        $match->load(['homeTeam.players','awayTeam.players']);
        $team = $match->home_team_id === (int)$data['team_id'] ? $match->homeTeam : ($match->away_team_id === (int)$data['team_id'] ? $match->awayTeam : null);
        abort_if(!$team, 422, 'Equipe não participa desta partida.');
        $ids = $team->players->pluck('id');
        abort_if($data['scorer_id'] && !$match->participations()->where('championship_player_id',$data['scorer_id'])->exists(),422,'O autor precisa estar marcado como participante.');
        if ($data['scorer_id']) abort_if(!$ids->contains($data['scorer_id']), 422, 'Autor do gol não pertence à equipe.');
        if ($data['assist_id']) abort_if(!$ids->contains($data['assist_id']) || $data['assist_id'] === $data['scorer_id'], 422, 'Assistência inválida.');
        $event=$match->events()->create($data); $this->recalculateMatchScore($match); return $event;
    }
    public function updateEvent(Request $request, ChampionshipMatchEvent $event) { $data=$request->validate(['minute'=>'nullable|integer|min:0|max:150','own_goal'=>'boolean']); $event->update($data); $this->recalculateMatchScore($event->match); return $event->fresh(); }
    public function deleteEvent(ChampionshipMatchEvent $event) { $match=$event->match; $event->delete(); $this->recalculateMatchScore($match); return ['message'=>'Evento removido.']; }
    public function setParticipation(Request $request, ChampionshipMatch $match)
    {
        $data=$request->validate(['championship_player_ids'=>'required|array','championship_player_ids.*'=>'exists:championship_players,id','mvp_id'=>'nullable|exists:championship_players,id']);
        $valid=$match->homeTeam->players->merge($match->awayTeam->players)->pluck('id'); abort_if(collect($data['championship_player_ids'])->diff($valid)->isNotEmpty(),422,'Participante não pertence a uma equipe do jogo.'); abort_if(isset($data['mvp_id']) && !in_array($data['mvp_id'],$data['championship_player_ids']),422,'O MVP precisa participar do jogo.');
        $match->participations()->delete(); foreach ($data['championship_player_ids'] as $id) ChampionshipMatchParticipation::create(['match_id'=>$match->id,'championship_player_id'=>$id,'mvp'=>$id==($data['mvp_id']??null)]); return $match->participations;
    }
    public function statistics(Request $request, Championship $championship, ChampionshipStatisticsService $statistics)
    {
        return collect($statistics->calculate($championship,$request->query('phase')))->sortByDesc('goal_participations')->values();
    }
    public function rankings(Request $request, Championship $championship, ChampionshipStatisticsService $statistics) { $stats=collect($statistics->calculate($championship,$request->query('phase'))); return ['scorers'=>$stats->sortByDesc('goals')->values(),'assists'=>$stats->sortByDesc('assists')->values(),'mvp'=>$stats->sortByDesc('mvp')->values(),'goalkeepers'=>$stats->where('position','goalkeeper')->sortBy('goals_conceded')->values()]; }
    public function calculateOdds(ChampionshipMatch $match, ChampionshipOddsService $service) { return $service->calculate($match); }
    public function awards(Championship $championship) { $players=$championship->players()->with('player')->get(); return $championship->awardCategories()->with('results.winner.player')->get()->map(function ($category) use ($players) { $category->candidates=$players->filter(fn($p)=>!$category->goalkeepers_only || $p->player_type==='goalkeeper')->values(); return $category; }); }
    public function vote(Request $request, ChampionshipAwardCategory $category)
    {
        abort_if(!$request->user()?->player_id, 403, 'Sua conta ainda não está vinculada a um jogador.');
        abort_if($category->type !== 'vote' || $category->published || !$category->voting_open, 422, 'Esta categoria não está recebendo votos.');
        abort_if(!$category->championship->players()->where('player_id',$request->user()->player_id)->exists(), 403, 'Você não está inscrito neste campeonato.');
        $data = $request->validate(['candidate_id'=>'required|exists:championship_players,id']);
        $candidate = ChampionshipPlayer::where('id',$data['candidate_id'])->where('championship_id', $category->championship_id)->firstOrFail();
        abort_if($category->goalkeepers_only && $candidate->player_type !== 'goalkeeper', 422, 'Candidato inelegível para esta categoria.');
        abort_if($candidate->player_id === $request->user()->player_id, 422, 'Você não pode votar em si mesmo.');
        abort_if(ChampionshipVote::where('championship_award_category_id',$category->id)->where('voter_id',$request->user()->id)->exists(), 422, 'Você já votou nesta categoria.');
        if (str_contains(mb_strtolower($category->name), 'xerif')) abort_if(!in_array(mb_strtolower((string)$candidate->sport_position), ['defensor','zagueiro','fixo','ala'], true), 422, 'Este candidato não está elegível para Xerifão.');
        return ChampionshipVote::create(['championship_award_category_id'=>$category->id,'voter_id'=>$request->user()->id,'candidate_id'=>$candidate->id]);
    }
    public function publishAwards(Championship $championship, ChampionshipAwardService $service)
    {
        abort_if($championship->status !== 'finished', 422, 'A votação só pode ser publicada após o encerramento esportivo.');
        $stats=collect(app(ChampionshipStatisticsService::class)->calculate($championship));
        foreach ($championship->awardCategories()->where('type','statistic')->get() as $category) { $field=str_contains($category->name,'Garçom')?'assists':'goals'; $winner=$stats->sortByDesc($field)->first(); if($winner) ChampionshipAwardResult::updateOrCreate(['championship_award_category_id'=>$category->id,'winner_id'=>$championship->players()->where('player_id',$winner['player']['id'])->value('id')],['votes'=>0,'percentage'=>100]); $category->update(['published'=>true]); }
        $championship->awardCategories()->where('type','vote')->each(function ($category) use ($service) { $service->consolidate($category); $category->update(['published'=>true]); });
        return $championship->awardCategories()->with('results')->get();
    }
    public function setVotingState(Request $request, ChampionshipAwardCategory $category) { $data=$request->validate(['voting_open'=>'required|boolean']); abort_if($category->published,422,'Esta categoria já foi publicada.'); $category->update(['voting_open'=>$data['voting_open']]); return $category->fresh(); }
    private function advancePlayoff(ChampionshipMatch $match): void
    {
        if ($match->phase === 'group') return;
        $winner = ($match->home_score > $match->away_score || (($match->home_score === $match->away_score) && $match->home_penalty_score > $match->away_penalty_score)) ? $match->home_team_id : $match->away_team_id;
        $match->update(['qualified_team_id'=>$winner]);
        $championship = $match->championship()->first();
        if ($match->phase === 'semi_final' && $championship->matches()->where('phase','semi_final')->where('status','finished')->count() === 2) {
            $winners = $championship->matches()->where('phase','semi_final')->pluck('qualified_team_id')->values();
            ChampionshipMatch::firstOrCreate(['championship_id'=>$championship->id,'phase'=>'challenger_final','home_team_id'=>$winners[0],'away_team_id'=>$winners[1]], ['status'=>'scheduled']);
        }
        if ($match->phase === 'challenger_final' && $match->status === 'finished') {
            $standing = app(ChampionshipStandingsService::class)->calculate($championship)[0]['team_id'];
            ChampionshipMatch::firstOrCreate(['championship_id'=>$championship->id,'phase'=>'final','home_team_id'=>$standing,'away_team_id'=>$winner], ['status'=>'scheduled']);
        }
        if ($match->phase === 'final' && $match->status === 'finished') $championship->update(['status'=>'finished','phase'=>'finished']);
    }
    private function recalculateMatchScore(ChampionshipMatch $match): void { $home=0;$away=0; foreach($match->events()->get() as $event){$scoredFor=$event->own_goal?($event->team_id===$match->home_team_id?$match->away_team_id:$match->home_team_id):$event->team_id; if($scoredFor===$match->home_team_id)$home++;else if($scoredFor===$match->away_team_id)$away++;} $match->update(['home_score'=>$home,'away_score'=>$away]); }
}
