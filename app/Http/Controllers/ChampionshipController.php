<?php

namespace App\Http\Controllers;

use App\Models\Championship;
use App\Models\ChampionshipAwardCategory;
use App\Models\ChampionshipAwardResult;
use App\Models\ChampionshipMatch;
use App\Models\ChampionshipMatchEvent;
use App\Models\ChampionshipMatchParticipation;
use App\Models\ChampionshipPlayer;
use App\Models\ChampionshipTeam;
use App\Models\ChampionshipTieBreaker;
use App\Models\ChampionshipVote;
use App\Models\Player;
use App\Models\User;
use App\Services\ChampionshipAuditService;
use App\Services\ChampionshipAwardService;
use App\Services\ChampionshipFixtureService;
use App\Services\ChampionshipOddsService;
use App\Services\ChampionshipPlayoffService;
use App\Services\ChampionshipStandingsService;
use App\Services\ChampionshipStatisticsService;
use App\Services\ChampionshipValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChampionshipController extends Controller
{
    public function index()
    {
        return Championship::withCount(['teams', 'players', 'matches'])->latest()->get();
    }

    public function show(Championship $championship, ChampionshipStandingsService $standings)
    {
        $matches = $championship->matches()->with(['homeTeam.players.player', 'awayTeam.players.player', 'odds'])->orderBy('phase')->orderBy('round')->get();
        $final = $matches->first(fn ($m) => $m->phase === 'final' && $m->status === 'finished');
        $table = $standings->calculate($championship);
        $champion = $final?->qualified_team_id ? $championship->teams()->with('players.player')->find($final->qualified_team_id) : null;
        if ($champion) {
            $champion->campaign = collect($table)->firstWhere('team_id', $champion->id);
            $champion->decisive_matches = $matches->whereIn('phase', ['semi_final', 'challenger_final', 'final'])->where('status', 'finished')->values();
        }

        return response()->json(['championship' => $championship->load(['teams.players.player', 'players.player']), 'standings' => $table, 'matches' => $matches, 'champion' => $champion]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'goalkeeper_rule_enabled' => 'boolean']);

        return DB::transaction(function () use ($data) {
            $championship = Championship::create($data);
            foreach (['Surubeiros FC', 'Hardworkers FC', 'Borracha Fraca FC', 'Fimose FC', 'Transformers FC'] as $name) {
                $championship->teams()->create(['name' => $name]);
            }
            app(ChampionshipAwardService::class)->seed($championship);

            return $championship->load('teams');
        });
    }

    public function registerPlayer(Request $request, Championship $championship)
    {
        abort_if($championship->status !== 'draft', 422, 'As inscrições só podem ser alteradas antes do início.');
        $data = $request->validate(['player_id' => 'required|exists:players,id', 'player_type' => 'required|in:field,goalkeeper', 'stone_group' => 'required|integer|min:1|max:5', 'stone_level' => 'required_if:player_type,field|nullable|numeric|decimal:1|between:1.1,5.5', 'sport_position' => 'nullable|string|max:30', 'team_id' => 'nullable|integer|exists:championship_teams,id']);
        $globalPlayer = Player::findOrFail($data['player_id']);
        abort_if(($data['player_type'] === 'goalkeeper') !== ($globalPlayer->position === 'goleiro'), 422, 'A posição do jogador não corresponde ao tipo da inscrição.');
        abort_if($championship->players()->where('player_id', $data['player_id'])->exists(), 422, 'Jogador já inscrito.');
        abort_if($data['player_type'] === 'field' && $championship->players()->where('player_type', 'field')->count() >= 25, 422, 'Limite de jogadores de linha atingido.');
        abort_if($data['player_type'] === 'goalkeeper' && $championship->players()->where('player_type', 'goalkeeper')->count() >= 5, 422, 'Limite de goleiros atingido.');
        if ($data['player_type'] === 'field') {
            $ratingInTenths = (int) round((float) $data['stone_level'] * 10);
            $ratingGroup = intdiv($ratingInTenths, 10);
            $ratingWithinGroup = $ratingInTenths % 10;
            abort_if($ratingGroup !== (int) $data['stone_group'] || $ratingWithinGroup < 1 || $ratingWithinGroup > 5, 422, 'A classificação precisa corresponder à Pedra e ficar entre x.1 e x.5.');
            abort_if($championship->players()->where('stone_level', $data['stone_level'])->exists(), 422, 'Esta classificação já foi usada.');
        }
        $team = null;
        if ($data['player_type'] === 'goalkeeper') {
            abort_if(empty($data['team_id']), 422, 'Selecione uma equipe para o goleiro.');
            $team = $championship->teams()->whereKey($data['team_id'])->first();
            abort_if(! $team, 422, 'A equipe selecionada não pertence ao campeonato.');
            abort_if($team->players()->where('player_type', 'goalkeeper')->exists(), 422, 'A equipe já possui um goleiro.');
        }

        return DB::transaction(function () use ($championship, $data, $team) {
            $participant = $championship->players()->create(collect($data)->except('team_id')->all());
            if ($team) {
                $team->players()->attach($participant->id);
            }

            return $participant->load('player');
        });
    }

    public function assignPlayers(Request $request, Championship $championship)
    {
        abort_if($championship->status !== 'draft', 422, 'A formação só pode ser alterada antes do início.');
        $data = $request->validate(['assignments' => 'required|array|size:5', 'assignments.*.team_id' => 'required|exists:championship_teams,id', 'assignments.*.championship_player_ids' => 'required|array|size:5', 'assignments.*.championship_player_ids.*' => 'exists:championship_players,id']);
        DB::transaction(function () use ($championship, $data) {
            $teamIds = collect($data['assignments'])->pluck('team_id');
            abort_if($teamIds->unique()->count() !== 5 || ChampionshipTeam::where('championship_id', $championship->id)->whereIn('id', $teamIds)->count() !== 5, 422, 'A formação deve usar exatamente os cinco times do campeonato.');
            $fieldPlayerIds = $championship->players()->where('player_type', 'field')->pluck('id');
            $championship->teams()->each(fn ($team) => $team->players()->detach($fieldPlayerIds));
            $seen = collect();
            foreach ($data['assignments'] as $assignment) {
                $ids = collect($assignment['championship_player_ids']);
                abort_if(ChampionshipPlayer::where('championship_id', $championship->id)->whereIn('id', $ids)->count() !== $ids->count(), 422, 'Há participantes de outro campeonato na formação.');
                abort_if($ids->intersect($seen)->isNotEmpty(), 422, 'Um jogador não pode estar em duas equipes.');
                abort_if($ids->count() !== $ids->unique()->count(), 422, 'Jogadores duplicados na equipe.');
                abort_if(ChampionshipPlayer::whereIn('id', $ids)->where('player_type', '!=', 'field')->exists(), 422, 'Somente jogadores de linha podem ocupar estas vagas.');
                $groups = ChampionshipPlayer::whereIn('id', $ids)->pluck('stone_group');
                abort_if($groups->count() !== $groups->unique()->count(), 422, 'Cada equipe precisa ter uma Pedra de cada grupo.');
                ChampionshipTeam::findOrFail($assignment['team_id'])->players()->attach($ids);
                $seen = $seen->merge($ids);
            }
        });

        return ['message' => 'Equipes formadas com sucesso.'];
    }

    public function generateFixtures(Championship $championship, ChampionshipFixtureService $service)
    {
        $service->generate($championship);

        return ['message' => 'Confrontos gerados.'];
    }

    public function start(Championship $championship, ChampionshipValidationService $validator, ChampionshipAuditService $audit)
    {
        abort_if($championship->status !== 'draft', 422, 'Somente um campeonato em rascunho pode ser iniciado.');
        $validator->validateReady($championship);
        $championship->update(['status' => 'active', 'phase' => 'group']);
        $audit->record($championship->id, 'championship.started', $championship);

        return $championship->fresh();
    }

    public function updateTeam(Request $request, ChampionshipTeam $team, ChampionshipAuditService $audit)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        abort_if($team->championship->status !== 'draft', 422, 'Times só podem ser alterados antes do início.');
        $team->update($data);
        $audit->record($team->championship_id, 'team.updated', $team, $data);

        return $team->fresh();
    }

    public function resolveTie(Request $request, Championship $championship, ChampionshipAuditService $audit)
    {
        abort_if($championship->phase !== 'group', 422, 'O desempate só pode ser resolvido na primeira fase.');
        $data = $request->validate([
            'team_a_id' => 'required|exists:championship_teams,id',
            'team_b_id' => 'required|exists:championship_teams,id',
            'winner_id' => 'required|exists:championship_teams,id',
        ]);
        $teamIds = [(int) $data['team_a_id'], (int) $data['team_b_id']];
        abort_if($teamIds[0] === $teamIds[1] || ! in_array((int) $data['winner_id'], $teamIds, true), 422, 'Equipes inválidas para o desempate.');
        abort_if($championship->teams()->whereIn('id', $teamIds)->count() !== 2, 422, 'As equipes precisam pertencer a este campeonato.');

        [$teamAId, $teamBId] = [min($teamIds), max($teamIds)];
        $tie = ChampionshipTieBreaker::updateOrCreate(
            ['championship_id' => $championship->id, 'team_a_id' => $teamAId, 'team_b_id' => $teamBId],
            ['winner_id' => $data['winner_id'], 'resolved_by' => $request->user()->id, 'reason' => 'administrative_draw'],
        );
        $audit->record($championship->id, 'standings.tie_breaker.resolved', $tie, $data);

        return $tie;
    }

    public function users()
    {
        return User::with('player')->orderBy('name')->get(['id', 'name', 'username', 'profile', 'player_id']);
    }

    public function linkUserPlayer(Request $request, User $user)
    {
        $data = $request->validate(['player_id' => 'nullable|exists:players,id']);
        abort_if($data['player_id'] && User::where('player_id', $data['player_id'])->where('id', '!=', $user->id)->exists(), 422, 'Este jogador já está vinculado a outro usuário.');
        $user->update(['player_id' => $data['player_id'] ?? null]);

        return $user->fresh('player');
    }

    public function generatePlayoffs(Championship $championship, ChampionshipPlayoffService $service)
    {
        $service->generate($championship);

        return ['message' => 'Semifinais geradas.'];
    }

    public function uploadCrest(Request $request, ChampionshipTeam $team, ChampionshipAuditService $audit)
    {
        $request->validate(['crest' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);
        abort_if($team->championship->status === 'finished', 422, 'O campeonato já foi encerrado.');
        if ($team->crest_path) {
            Storage::disk('public')->delete($team->crest_path);
        }
        $team->update(['crest_path' => $request->file('crest')->store('championship-crests', 'public')]);
        $audit->record($team->championship_id, 'team.crest.updated', $team);

        return $team->fresh();
    }

    public function removeCrest(ChampionshipTeam $team, ChampionshipAuditService $audit)
    {
        if ($team->crest_path) {
            Storage::disk('public')->delete($team->crest_path);
        } $team->update(['crest_path' => null]);
        $audit->record($team->championship_id, 'team.crest.removed', $team);

        return $team->fresh();
    }

    public function updateMatch(Request $request, ChampionshipMatch $match)
    {
        $data = $request->validate(['home_score' => 'required|integer|min:0', 'away_score' => 'required|integer|min:0', 'home_penalty_score' => 'nullable|integer|min:0', 'away_penalty_score' => 'nullable|integer|min:0', 'status' => 'required|in:finished,cancelled']);
        foreach (['home_score', 'away_score', 'home_penalty_score', 'away_penalty_score'] as $scoreField) {
            if (isset($data[$scoreField])) {
                $data[$scoreField] = (int) $data[$scoreField];
            }
        }
        if ($match->phase !== 'group' && $data['home_score'] === $data['away_score']) {
            abort_if(! isset($data['home_penalty_score'], $data['away_penalty_score']), 422, 'Eliminatórias empatadas exigem pênaltis.');
            abort_if($data['home_penalty_score'] === $data['away_penalty_score'], 422, 'Os pênaltis precisam definir um vencedor.');
        }
        $match->update($data);
        if ($data['status'] === 'finished') {
            $this->advancePlayoff($match->fresh());
        }

        return $match->fresh(['homeTeam', 'awayTeam']);
    }

    public function addEvent(Request $request, ChampionshipMatch $match)
    {
        abort_if($match->status === 'finished', 422, 'Partidas finalizadas não podem receber novos eventos.');
        $data = array_merge(
            ['scorer_id' => null, 'assist_id' => null, 'own_goal' => false, 'minute' => null],
            $request->validate(['scorer_id' => 'nullable|exists:championship_players,id', 'assist_id' => 'nullable|exists:championship_players,id', 'team_id' => 'required|exists:championship_teams,id', 'own_goal' => 'boolean', 'minute' => 'nullable|integer|min:0|max:150']),
        );
        $match->load(['homeTeam.players', 'awayTeam.players']);
        $team = $match->home_team_id === (int) $data['team_id'] ? $match->homeTeam : ($match->away_team_id === (int) $data['team_id'] ? $match->awayTeam : null);
        abort_if(! $team, 422, 'Equipe não participa desta partida.');
        $ids = $team->players->pluck('id');
        abort_if($data['scorer_id'] && ! $match->participations()->where('championship_player_id', $data['scorer_id'])->exists(), 422, 'O autor precisa estar marcado como participante.');
        if ($data['scorer_id']) {
            abort_if(! $ids->contains($data['scorer_id']), 422, 'Autor do gol não pertence à equipe.');
        }
        if ($data['assist_id']) {
            abort_if(! $data['scorer_id'], 422, 'A assistência precisa estar vinculada a um autor do gol.');
            abort_if(! $ids->contains($data['assist_id']) || (int) $data['assist_id'] === (int) $data['scorer_id'], 422, 'Assistência inválida.');
            abort_if(! $match->participations()->where('championship_player_id', $data['assist_id'])->exists(), 422, 'O assistente precisa estar marcado como participante.');
        }
        $event = $match->events()->create($data);
        $this->recalculateMatchScore($match);

        return $event;
    }

    public function updateEvent(Request $request, ChampionshipMatchEvent $event)
    {
        $data = $request->validate(['minute' => 'nullable|integer|min:0|max:150', 'own_goal' => 'boolean']);
        $event->update($data);
        $this->recalculateMatchScore($event->match);

        return $event->fresh();
    }

    public function deleteEvent(ChampionshipMatchEvent $event)
    {
        $match = $event->match;
        $event->delete();
        $this->recalculateMatchScore($match);

        return ['message' => 'Evento removido.'];
    }

    public function setParticipation(Request $request, ChampionshipMatch $match)
    {
        $data = $request->validate([
            'championship_player_ids' => 'required|array',
            'championship_player_ids.*' => 'required|integer|distinct|exists:championship_players,id',
            'mvp_id' => 'nullable|exists:championship_players,id',
        ]);

        return DB::transaction(function () use ($match, $data) {
            $valid = $match->homeTeam->players->merge($match->awayTeam->players)->pluck('id');
            abort_if(collect($data['championship_player_ids'])->diff($valid)->isNotEmpty(), 422, 'Participante não pertence a uma equipe do jogo.');
            abort_if(isset($data['mvp_id']) && ! in_array((int) $data['mvp_id'], array_map('intval', $data['championship_player_ids']), true), 422, 'O MVP precisa participar do jogo.');

            $match->participations()->delete();
            foreach ($data['championship_player_ids'] as $id) {
                ChampionshipMatchParticipation::create([
                    'match_id' => $match->id,
                    'championship_player_id' => $id,
                    'mvp' => (int) $id === (int) ($data['mvp_id'] ?? null),
                ]);
            }

            return $match->participations()->get();
        });
    }

    public function statistics(Request $request, Championship $championship, ChampionshipStatisticsService $statistics)
    {
        return collect($statistics->calculate($championship, $request->query('phase')))->sortByDesc('goal_participations')->values();
    }

    public function rankings(Request $request, Championship $championship, ChampionshipStatisticsService $statistics)
    {
        $stats = collect($statistics->calculate($championship, $request->query('phase')));

        return ['scorers' => $stats->sortByDesc('goals')->values(), 'assists' => $stats->sortByDesc('assists')->values(), 'mvp' => $stats->sortByDesc('mvp')->values(), 'goalkeepers' => $stats->where('position', 'goalkeeper')->sortBy('goals_conceded')->values()];
    }

    public function calculateOdds(ChampionshipMatch $match, ChampionshipOddsService $service)
    {
        return $service->calculate($match);
    }

    public function awards(Championship $championship)
    {
        $players = $championship->players()->with('player')->get();

        return $championship->awardCategories()->with('results.winner.player')->get()->map(function ($category) use ($players) {
            $category->candidates = $players->filter(fn ($p) => ! $category->goalkeepers_only || $p->player_type === 'goalkeeper')->values();

            return $category;
        });
    }

    public function vote(Request $request, ChampionshipAwardCategory $category)
    {
        abort_if(! $request->user()?->player_id, 403, 'Sua conta ainda não está vinculada a um jogador.');
        $data = $request->validate(['candidate_id' => 'required|exists:championship_players,id']);

        try {
            return DB::transaction(function () use ($category, $data, $request) {
                $participant = ChampionshipPlayer::query()
                    ->where('championship_id', $category->championship_id)
                    ->where('player_id', $request->user()->player_id)
                    ->lockForUpdate()
                    ->first();
                abort_if(! $participant, 403, 'Você não está inscrito neste campeonato.');

                $category = ChampionshipAwardCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
                abort_if($category->type !== 'vote' || $category->published || ! $category->voting_open, 422, 'Esta categoria não está recebendo votos.');

                $championship = Championship::query()->whereKey($category->championship_id)->lockForUpdate()->firstOrFail();
                abort_if($championship->status !== 'finished', 422, 'A votação só será liberada após o encerramento do campeonato.');

                $candidate = ChampionshipPlayer::query()
                    ->where('id', $data['candidate_id'])
                    ->where('championship_id', $category->championship_id)
                    ->firstOrFail();
                abort_if($category->goalkeepers_only && $candidate->player_type !== 'goalkeeper', 422, 'Candidato inelegível para esta categoria.');
                abort_if($candidate->id === $participant->id, 422, 'Você não pode votar em si mesmo.');
                if (str_contains(mb_strtolower($category->name), 'xerif')) {
                    abort_if(! in_array(mb_strtolower((string) $candidate->sport_position), ['defensor', 'zagueiro', 'fixo', 'ala'], true), 422, 'Este candidato não está elegível para Xerifão.');
                }

                $participantVoterIds = DB::table('users')->where('player_id', $participant->player_id)->select('id');
                $alreadyVoted = $category->votes()->where(function ($query) use ($participant, $participantVoterIds) {
                    $query->where('voter_championship_player_id', $participant->id)
                        ->orWhereIn('voter_id', $participantVoterIds);
                })->exists();
                abort_if($alreadyVoted, 422, 'Você já votou nesta categoria.');

                return ChampionshipVote::create([
                    'championship_award_category_id' => $category->id,
                    'voter_id' => $request->user()->id,
                    'championship_voter_id' => null,
                    'voter_championship_player_id' => $participant->id,
                    'candidate_id' => $candidate->id,
                ]);
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                abort(422, 'Você já votou nesta categoria.');
            }

            throw $exception;
        }
    }

    public function publishAwards(Championship $championship, ChampionshipAwardService $service)
    {
        abort_if($championship->status !== 'finished', 422, 'A votação só pode ser publicada após o encerramento esportivo.');
        abort_if($championship->awardCategories()->where('type', 'vote')->where('published', false)->where('voting_open', true)->exists(), 422, 'Encerre a votação antes de publicar os resultados.');
        $stats = collect(app(ChampionshipStatisticsService::class)->calculate($championship));
        foreach ($championship->awardCategories()->where('type', 'statistic')->get() as $category) {
            $field = str_contains($category->name, 'Garçom') ? 'assists' : 'goals';
            $winner = $stats->sortByDesc($field)->first();
            if ($winner) {
                ChampionshipAwardResult::updateOrCreate(['championship_award_category_id' => $category->id, 'winner_id' => $championship->players()->where('player_id', $winner['player']['id'])->value('id')], ['votes' => 0, 'percentage' => 100]);
            } $category->update(['published' => true]);
        }
        $championship->awardCategories()->where('type', 'vote')->each(function ($category) use ($service) {
            $service->consolidate($category);
            $category->update(['published' => true]);
        });

        return $championship->awardCategories()->with('results')->get();
    }

    public function endVoting(Championship $championship, ChampionshipAwardService $service, ChampionshipAuditService $audit)
    {
        abort_if($championship->status !== 'finished', 422, 'A votação só pode ser encerrada após o encerramento esportivo.');
        $closed = $service->endVoting($championship);
        $audit->record($championship->id, 'awards.voting.ended', null, ['categories_closed' => $closed]);

        return $championship->awardCategories()->with('results.winner.player')->get();
    }

    private function advancePlayoff(ChampionshipMatch $match): void
    {
        if ($match->phase === 'group') {
            return;
        }
        $winner = ($match->home_score > $match->away_score || (($match->home_score === $match->away_score) && $match->home_penalty_score > $match->away_penalty_score)) ? $match->home_team_id : $match->away_team_id;
        $match->update(['qualified_team_id' => $winner]);
        $championship = $match->championship()->first();
        if ($match->phase === 'semi_final' && $championship->matches()->where('phase', 'semi_final')->where('status', 'finished')->count() === 2) {
            $winners = $championship->matches()->where('phase', 'semi_final')->pluck('qualified_team_id')->values();
            ChampionshipMatch::firstOrCreate(['championship_id' => $championship->id, 'phase' => 'challenger_final', 'home_team_id' => $winners[0], 'away_team_id' => $winners[1]], ['status' => 'scheduled']);
        }
        if ($match->phase === 'challenger_final' && $match->status === 'finished') {
            $standing = app(ChampionshipStandingsService::class)->calculate($championship)[0]['team_id'];
            ChampionshipMatch::firstOrCreate(['championship_id' => $championship->id, 'phase' => 'final', 'home_team_id' => $standing, 'away_team_id' => $winner], ['status' => 'scheduled']);
        }
        if ($match->phase === 'final' && $match->status === 'finished') {
            $championship->update(['status' => 'finished', 'phase' => 'finished']);
        }
    }

    private function recalculateMatchScore(ChampionshipMatch $match): void
    {
        $home = 0;
        $away = 0;
        foreach ($match->events()->get() as $event) {
            $scoredFor = $event->own_goal ? ($event->team_id === $match->home_team_id ? $match->away_team_id : $match->home_team_id) : $event->team_id;
            if ($scoredFor === $match->home_team_id) {
                $home++;
            } elseif ($scoredFor === $match->away_team_id) {
                $away++;
            }
        } $match->update(['home_score' => $home, 'away_score' => $away]);
    }
}
