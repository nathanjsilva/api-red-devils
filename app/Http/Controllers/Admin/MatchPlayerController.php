<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMatchPlayerRequest;
use App\Http\Requests\UpdateMatchPlayerRequest;
use App\Http\Resources\MatchPlayerResource;
use App\Models\MatchPlayer;
use App\Models\Pelada;
use App\Models\Player;

class MatchPlayerController extends Controller
{
    public function store(StoreMatchPlayerRequest $request)
    {
        $matchPlayer = MatchPlayer::create($request->validated());

        return new MatchPlayerResource($matchPlayer);
    }

    public function update(UpdateMatchPlayerRequest $request, $id)
    {
        $matchPlayer = MatchPlayer::with(['player', 'pelada'])->find($id);

        if (! $matchPlayer) {
            return $this->errorResponse('Registro não encontrado.', 404);
        }

        $matchPlayer->update($request->validated());
        $matchPlayer->refresh()->load(['player', 'pelada']);

        return new MatchPlayerResource($matchPlayer);
    }

    public function destroy($id)
    {
        $matchPlayer = MatchPlayer::find($id);

        if (! $matchPlayer) {
            return $this->errorResponse('Registro não encontrado.', 404);
        }

        $matchPlayer->delete();

        return response()->json(['message' => 'Registro removido com sucesso.']);
    }

    /**
     * Cria ou atualiza as estatísticas de um jogador numa pelada, identificando
     * o registro pelo par (player_id, pelada_id) em vez de um id próprio.
     */
    public function upsertByPlayerAndPelada(UpdateMatchPlayerRequest $request, $peladaId, $playerId)
    {
        $pelada = Pelada::find($peladaId);
        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $player = Player::find($playerId);
        if (! $player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        $validated = $request->validated();
        unset($validated['player_id'], $validated['pelada_id']);

        $matchPlayer = MatchPlayer::updateOrCreate(
            [
                'player_id' => $playerId,
                'pelada_id' => $peladaId,
            ],
            $validated
        );

        $matchPlayer->load(['player', 'pelada']);

        return new MatchPlayerResource($matchPlayer);
    }
}
