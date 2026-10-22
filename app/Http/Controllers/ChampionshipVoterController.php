<?php

namespace App\Http\Controllers;

use App\Models\Championship;
use App\Models\ChampionshipAwardCategory;
use App\Models\ChampionshipPlayer;
use App\Models\ChampionshipVote;
use App\Models\ChampionshipVoter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ChampionshipVoterController extends Controller
{
    public function players(Championship $championship): JsonResponse
    {
        $players = $championship->players()
            ->with('player:id,name,nickname')
            ->orderBy('id')
            ->get(['id', 'championship_id', 'player_id', 'player_type'])
            ->map(fn (ChampionshipPlayer $participant) => [
                'id' => $participant->id,
                'name' => $participant->player?->name,
                'nickname' => $participant->player?->nickname,
                'player_type' => $participant->player_type,
            ]);

        return response()->json($players);
    }

    public function register(Request $request, Championship $championship): JsonResponse
    {
        $data = $request->validate([
            'championship_player_id' => 'required|integer|exists:championship_players,id',
            'pin' => 'required|digits:6|confirmed',
        ]);

        $participant = $championship->players()->whereKey($data['championship_player_id'])->first();
        abort_if(! $participant, 422, 'O jogador selecionado não pertence a este campeonato.');

        try {
            [$voter, $token] = DB::transaction(function () use ($championship, $participant, $data) {
                $lockedParticipant = ChampionshipPlayer::query()->whereKey($participant->id)->lockForUpdate()->firstOrFail();
                abort_if(ChampionshipVoter::query()
                    ->where('championship_id', $championship->id)
                    ->where('championship_player_id', $lockedParticipant->id)
                    ->exists(), 409, 'Este jogador já possui cadastro para votar.');

                $voter = ChampionshipVoter::create([
                    'championship_id' => $championship->id,
                    'championship_player_id' => $lockedParticipant->id,
                    'pin_hash' => Hash::make($data['pin']),
                ]);

                return [$voter, $this->issueSession($voter)];
            });
        } catch (Throwable $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                abort(409, 'Este jogador já possui cadastro para votar.');
            }

            throw $exception;
        }

        return response()->json([
            'token' => $token,
            'voter' => $this->voterSummary($voter->fresh(['championshipPlayer.player'])),
        ], 201);
    }

    public function login(Request $request, Championship $championship): JsonResponse
    {
        $data = $request->validate([
            'championship_player_id' => 'required|integer',
            'pin' => 'required|digits:6',
        ]);

        $limiterKey = $this->loginLimiterKey($request, $championship->id);
        if (RateLimiter::tooManyAttempts($limiterKey, 20)) {
            throw new HttpException(429, 'Muitas tentativas de identificação. Tente novamente em um minuto.');
        }
        RateLimiter::hit($limiterKey, 60);

        $result = DB::transaction(function () use ($championship, $data) {
            $voter = ChampionshipVoter::query()
                ->where('championship_id', $championship->id)
                ->where('championship_player_id', $data['championship_player_id'])
                ->lockForUpdate()
                ->first();

            if (! $voter) {
                return ['state' => 'invalid'];
            }

            if ($voter->locked_until && $voter->locked_until->isFuture()) {
                return ['state' => 'locked'];
            }

            if ($voter->locked_until) {
                $voter->failed_attempts = 0;
                $voter->locked_until = null;
            }

            if (! Hash::check($data['pin'], $voter->pin_hash)) {
                $voter->failed_attempts++;
                if ($voter->failed_attempts >= 5) {
                    $voter->failed_attempts = 5;
                    $voter->locked_until = now()->addMinutes(15);
                }
                $voter->save();

                return ['state' => $voter->locked_until ? 'locked' : 'invalid'];
            }

            $voter->failed_attempts = 0;
            $voter->locked_until = null;
            $token = $this->issueSession($voter);

            return ['state' => 'ok', 'voter' => $voter->fresh(['championshipPlayer.player']), 'token' => $token];
        });

        if ($result['state'] !== 'ok') {
            $message = $result['state'] === 'locked'
                ? 'Identificação temporariamente bloqueada. Tente novamente em 15 minutos.'
                : 'Não foi possível identificar o votante. Confira o jogador e o PIN.';

            return response()->json(['message' => $message], $result['state'] === 'locked' ? 429 : 422);
        }

        RateLimiter::clear($limiterKey);

        return response()->json([
            'token' => $result['token'],
            'voter' => $this->voterSummary($result['voter']),
        ]);
    }

    public function status(Request $request, Championship $championship): JsonResponse
    {
        $voter = $this->authenticatedVoter($request, $championship->id);
        $participantId = $voter->championship_player_id;
        $globalPlayerId = $voter->championshipPlayer?->player_id;
        $legacyVoterIds = $globalPlayerId
            ? DB::table('users')->where('player_id', $globalPlayerId)->select('id')
            : DB::table('users')->whereRaw('1 = 0')->select('id');

        $categories = $championship->awardCategories()->orderBy('id')->get()->map(function (ChampionshipAwardCategory $category) use ($voter, $participantId, $legacyVoterIds) {
            $hasVoted = $category->votes()->where(function ($query) use ($voter, $participantId, $legacyVoterIds) {
                $query->where('championship_voter_id', $voter->id)
                    ->orWhere('voter_championship_player_id', $participantId)
                    ->orWhereIn('voter_id', $legacyVoterIds);
            })->exists();

            return [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'voting_open' => $category->voting_open,
                'published' => $category->published,
                'has_voted' => $hasVoted,
            ];
        });

        return response()->json([
            'voter' => $this->voterSummary($voter),
            'categories' => $categories,
        ]);
    }

    public function logout(Request $request, Championship $championship): JsonResponse
    {
        $voter = $this->authenticatedVoter($request, $championship->id);
        $voter->forceFill(['session_token_hash' => null, 'session_expires_at' => null])->save();

        return response()->json(['message' => 'Sessão de votação encerrada.']);
    }

    public function vote(Request $request, ChampionshipAwardCategory $category): JsonResponse
    {
        $data = $request->validate(['candidate_id' => 'required|integer|exists:championship_players,id']);
        [$voter, $tokenHash] = $this->authenticatedVoterWithToken($request, $category->championship_id);

        try {
            $vote = DB::transaction(function () use ($category, $data, $voter, $tokenHash) {
                $voter = ChampionshipVoter::query()->whereKey($voter->id)->lockForUpdate()->firstOrFail();
                abort_if($voter->session_token_hash !== $tokenHash || ! $voter->session_expires_at?->isFuture(), 401, 'A sessão de votação expirou. Identifique-se novamente.');

                $category = ChampionshipAwardCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
                abort_if($category->type !== 'vote' || $category->published || ! $category->voting_open, 422, 'Esta categoria não está recebendo votos.');

                $candidate = ChampionshipPlayer::query()
                    ->whereKey($data['candidate_id'])
                    ->where('championship_id', $category->championship_id)
                    ->firstOrFail();
                abort_if($candidate->id === $voter->championship_player_id, 422, 'Você não pode votar em si mesmo.');
                abort_if($category->goalkeepers_only && $candidate->player_type !== 'goalkeeper', 422, 'Candidato inelegível para esta categoria.');
                if (str_contains(mb_strtolower($category->name), 'xerif')) {
                    abort_if(! in_array(mb_strtolower((string) $candidate->sport_position), ['defensor', 'zagueiro', 'fixo', 'ala'], true), 422, 'Este candidato não está elegível para Xerifão.');
                }

                $participant = $voter->championshipPlayer;
                $hasLegacyVote = $category->votes()
                    ->whereIn('voter_id', DB::table('users')->where('player_id', $participant->player_id)->select('id'))
                    ->exists();
                $hasVoted = $category->votes()
                    ->where(function ($query) use ($voter, $participant) {
                        $query->where('championship_voter_id', $voter->id)
                            ->orWhere('voter_championship_player_id', $participant->id);
                    })
                    ->exists();
                abort_if($hasLegacyVote || $hasVoted, 422, 'Você já votou nesta categoria.');

                return ChampionshipVote::create([
                    'championship_award_category_id' => $category->id,
                    'voter_id' => null,
                    'championship_voter_id' => $voter->id,
                    'voter_championship_player_id' => $participant->id,
                    'candidate_id' => $candidate->id,
                ]);
            });
        } catch (Throwable $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw ValidationException::withMessages(['candidate_id' => 'Você já votou nesta categoria.']);
            }

            throw $exception;
        }

        return response()->json($vote, 201);
    }

    /** @return array{0: ChampionshipVoter, 1: string} */
    private function authenticatedVoterWithToken(Request $request, int $championshipId): array
    {
        $token = (string) $request->header('X-Championship-Voter-Session', '');
        abort_if($token === '', 401, 'Identifique-se para votar.');

        $tokenHash = hash('sha256', $token);
        $voter = ChampionshipVoter::query()
            ->with(['championshipPlayer.player'])
            ->where('championship_id', $championshipId)
            ->where('session_token_hash', $tokenHash)
            ->first();

        if (! $voter || ! $voter->session_expires_at?->isFuture()) {
            if ($voter) {
                $voter->forceFill(['session_token_hash' => null, 'session_expires_at' => null])->save();
            }
            abort(401, 'A sessão de votação expirou. Identifique-se novamente.');
        }

        return [$voter, $tokenHash];
    }

    private function authenticatedVoter(Request $request, int $championshipId): ChampionshipVoter
    {
        return $this->authenticatedVoterWithToken($request, $championshipId)[0];
    }

    private function issueSession(ChampionshipVoter $voter): string
    {
        $token = Str::random(80);
        $voter->forceFill([
            'session_token_hash' => hash('sha256', $token),
            'session_expires_at' => now()->addHours(12),
            'failed_attempts' => 0,
            'locked_until' => null,
        ])->save();

        return $token;
    }

    private function voterSummary(ChampionshipVoter $voter): array
    {
        return [
            'championship_player_id' => $voter->championship_player_id,
            'player' => [
                'name' => $voter->championshipPlayer?->player?->name,
                'nickname' => $voter->championshipPlayer?->player?->nickname,
            ],
        ];
    }

    private function loginLimiterKey(Request $request, int $championshipId): string
    {
        return 'championship-voter-login:'.$championshipId.':'.hash('sha256', (string) $request->ip());
    }

    private function isUniqueConstraintViolation(Throwable $exception): bool
    {
        return $exception instanceof \Illuminate\Database\QueryException
            && in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
