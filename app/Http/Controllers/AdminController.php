<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminStorePlayerRequest;
use App\Http\Requests\StoreMatchPlayerRequest;
use App\Http\Requests\StorePeladaRequest;
use App\Http\Requests\UpdateMatchPlayerRequest;
use App\Http\Requests\UpdatePeladaRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Http\Resources\MatchPlayerResource;
use App\Http\Resources\PeladaResource;
use App\Http\Resources\PlayerResource;
use App\Models\MatchPlayer;
use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function storePlayer(AdminStorePlayerRequest $request)
    {
        $player = Player::create($request->validated());

        return new PlayerResource($player);
    }

    public function updatePlayer(UpdatePlayerRequest $request, $id)
    {
        $player = Player::find($id);

        if (!$player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        $player->update($request->validated());

        return new PlayerResource($player);
    }

    public function deletePlayer($id)
    {
        $player = Player::find($id);

        if (!$player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        $player->delete();

        return response()->json(['message' => 'Jogador deletado com sucesso.']);
    }

    public function listPeladas()
    {
        return PeladaResource::collection(Pelada::with('players')->get());
    }

    public function showPelada($id)
    {
        $pelada = Pelada::with('players')->find($id);

        if (!$pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        return new PeladaResource($pelada);
    }

    public function getPeladasByDate($date)
    {
        $peladas = Pelada::whereDate('date', $date)
            ->with('players')
            ->get();

        if ($peladas->isEmpty()) {
            return $this->errorResponse('Nenhuma pelada encontrada para esta data.', 404);
        }

        return PeladaResource::collection($peladas);
    }

    public function storePelada(StorePeladaRequest $request)
    {
        $pelada = Pelada::create($request->validated());

        return new PeladaResource($pelada);
    }

    public function updatePelada(UpdatePeladaRequest $request, $id)
    {
        $pelada = Pelada::find($id);

        if (!$pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $pelada->update($request->validated());

        return new PeladaResource($pelada);
    }

    public function deletePelada($id)
    {
        $pelada = Pelada::find($id);

        if (!$pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $pelada->delete();

        return response()->json(['message' => 'Pelada deletada com sucesso.']);
    }

    public function storeMatchPlayer(StoreMatchPlayerRequest $request)
    {
        $matchPlayer = MatchPlayer::create($request->validated());

        return new MatchPlayerResource($matchPlayer);
    }

    public function updateMatchPlayer(UpdateMatchPlayerRequest $request, $id)
    {
        $matchPlayer = MatchPlayer::with(['player', 'pelada'])->find($id);

        if (!$matchPlayer) {
            return $this->errorResponse('Registro não encontrado.', 404);
        }

        $matchPlayer->update($request->validated());
        $matchPlayer->refresh()->load(['player', 'pelada']);

        return new MatchPlayerResource($matchPlayer);
    }

    public function updateMatchPlayerByPlayerAndPelada(UpdateMatchPlayerRequest $request, $peladaId, $playerId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $player = Player::find($playerId);
        if (!$player) {
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

    public function deleteMatchPlayer($id)
    {
        $matchPlayer = MatchPlayer::find($id);

        if (!$matchPlayer) {
            return $this->errorResponse('Registro não encontrado.', 404);
        }

        $matchPlayer->delete();

        return response()->json(['message' => 'Registro removido com sucesso.']);
    }

    public function organizeTeams(Request $request, $peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $request->validate([
            'player_ids' => 'required|array|min:' . ($pelada->qtd_times * $pelada->qtd_jogadores_por_time),
            'player_ids.*' => 'exists:players,id',
        ]);

        $existingTeams = Team::where('pelada_id', $peladaId)->get();
        foreach ($existingTeams as $team) {
            $team->players()->detach();
            $team->delete();
        }

        $players = Player::whereIn('id', $request->player_ids)->get();
        $goalkeepers = $players->where('position', 'goleiro');
        $fieldPlayers = $players->where('position', 'linha');

        if ($goalkeepers->count() < $pelada->qtd_goleiros) {
            return $this->errorResponse('Número insuficiente de goleiros.', 400);
        }

        $teams = [];
        for ($i = 1; $i <= $pelada->qtd_times; $i++) {
            $team = Team::create([
                'pelada_id' => $peladaId,
                'name' => 'Time ' . $i,
            ]);
            $teams[] = $team;
        }

        $goalkeepersArray = $goalkeepers->values()->all();
        $fieldPlayersArray = $fieldPlayers->values()->all();

        $goalkeeperIndex = 0;
        foreach ($teams as $team) {
            $goalkeepersNeeded = min(2, count($goalkeepersArray) - $goalkeeperIndex);
            for ($g = 0; $g < $goalkeepersNeeded && $goalkeeperIndex < count($goalkeepersArray); $g++) {
                $team->players()->attach($goalkeepersArray[$goalkeeperIndex]->id);
                $goalkeeperIndex++;
            }
        }

        $fieldPlayerIndex = 0;
        $playersPerTeam = floor(count($fieldPlayersArray) / $pelada->qtd_times);

        foreach ($teams as $team) {
            for ($p = 0; $p < $playersPerTeam && $fieldPlayerIndex < count($fieldPlayersArray); $p++) {
                $team->players()->attach($fieldPlayersArray[$fieldPlayerIndex]->id);
                $fieldPlayerIndex++;
            }
        }

        $teamsWithPlayers = Team::where('pelada_id', $peladaId)->with('players')->get();

        return response()->json([
            'message' => 'Times organizados com sucesso.',
            'teams' => $teamsWithPlayers->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'players' => $team->players,
                ];
            }),
        ]);
    }
}
