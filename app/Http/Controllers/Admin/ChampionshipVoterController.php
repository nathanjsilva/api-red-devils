<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Championship;
use App\Models\ChampionshipPlayer;
use App\Models\ChampionshipVote;
use App\Models\ChampionshipVoter;
use App\Services\ChampionshipAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class ChampionshipVoterController extends Controller
{
    public function index(Championship $championship): JsonResponse
    {
        $voters = $championship->voters()
            ->with(['championshipPlayer.player', 'votes.category'])
            ->orderBy('id')
            ->get()
            ->map(function (ChampionshipVoter $voter) use ($championship) {
                $globalPlayerId = $voter->championshipPlayer?->player_id;
                $legacyVoterIds = $globalPlayerId
                    ? DB::table('users')->where('player_id', $globalPlayerId)->select('id')
                    : DB::table('users')->whereRaw('1 = 0')->select('id');
                $legacyCategories = ChampionshipVote::query()
                    ->whereIn('voter_id', $legacyVoterIds)
                    ->whereHas('category', fn ($query) => $query->where('championship_id', $championship->id))
                    ->with('category')
                    ->get()
                    ->pluck('category');
                $categories = $voter->votes
                    ->pluck('category')
                    ->merge($legacyCategories)
                    ->filter()
                    ->unique('id')
                    ->values()
                    ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name]);

                return [
                    'id' => $voter->id,
                    'championship_player_id' => $voter->championship_player_id,
                    'player' => [
                        'id' => $voter->championshipPlayer?->player?->id,
                        'name' => $voter->championshipPlayer?->player?->name,
                        'nickname' => $voter->championshipPlayer?->player?->nickname,
                    ],
                    'categories_voted' => $categories,
                ];
            });

        return response()->json($voters);
    }

    public function updatePlayer(Request $request, ChampionshipVoter $voter, ChampionshipAuditService $audit): JsonResponse
    {
        $data = $request->validate(['championship_player_id' => 'required|integer|exists:championship_players,id']);
        $newParticipant = ChampionshipPlayer::query()
            ->where('championship_id', $voter->championship_id)
            ->whereKey($data['championship_player_id'])
            ->first();
        abort_if(! $newParticipant, 422, 'O jogador selecionado não pertence ao campeonato do cadastro.');

        try {
            $voter = DB::transaction(function () use ($voter, $newParticipant, $audit) {
                $lockedVoter = ChampionshipVoter::query()->whereKey($voter->id)->lockForUpdate()->firstOrFail();
                $conflict = ChampionshipVoter::query()
                    ->where('championship_id', $lockedVoter->championship_id)
                    ->where('championship_player_id', $newParticipant->id)
                    ->where('id', '<>', $lockedVoter->id)
                    ->exists();
                abort_if($conflict, 409, 'Este jogador já possui outro cadastro de votação.');

                $previousParticipantId = $lockedVoter->championship_player_id;
                if ($previousParticipantId === $newParticipant->id) {
                    return $lockedVoter->fresh(['championshipPlayer.player']);
                }

                $lockedVoter->forceFill([
                    'championship_player_id' => $newParticipant->id,
                    'session_token_hash' => null,
                    'session_expires_at' => null,
                ])->save();

                $audit->record($lockedVoter->championship_id, 'voter.player.relinked', $lockedVoter, [
                    'previous_championship_player_id' => $previousParticipantId,
                    'new_championship_player_id' => $newParticipant->id,
                ]);

                return $lockedVoter->fresh(['championshipPlayer.player']);
            });
        } catch (Throwable $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                abort(409, 'Este jogador já possui outro cadastro de votação.');
            }

            throw $exception;
        }

        return response()->json($this->summary($voter));
    }

    public function resetPin(Request $request, ChampionshipVoter $voter, ChampionshipAuditService $audit): JsonResponse
    {
        $data = $request->validate(['pin' => 'required|digits:6|confirmed']);

        DB::transaction(function () use ($voter, $data, $audit) {
            $lockedVoter = ChampionshipVoter::query()->whereKey($voter->id)->lockForUpdate()->firstOrFail();
            $lockedVoter->forceFill([
                'pin_hash' => Hash::make($data['pin']),
                'failed_attempts' => 0,
                'locked_until' => null,
                'session_token_hash' => null,
                'session_expires_at' => null,
            ])->save();

            $audit->record($lockedVoter->championship_id, 'voter.pin.reset', $lockedVoter);
        });

        return response()->json(['message' => 'PIN redefinido. O votante deverá se identificar novamente.']);
    }

    private function summary(ChampionshipVoter $voter): array
    {
        return [
            'id' => $voter->id,
            'championship_player_id' => $voter->championship_player_id,
            'player' => [
                'id' => $voter->championshipPlayer?->player?->id,
                'name' => $voter->championshipPlayer?->player?->name,
                'nickname' => $voter->championshipPlayer?->player?->nickname,
            ],
        ];
    }

    private function isUniqueConstraintViolation(Throwable $exception): bool
    {
        return $exception instanceof \Illuminate\Database\QueryException
            && in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
