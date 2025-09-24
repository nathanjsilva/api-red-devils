<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminStorePlayerRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Http\Requests\StorePeladaRequest;
use App\Http\Requests\UpdatePeladaRequest;
use App\Http\Requests\StoreMatchPlayerRequest;
use App\Http\Requests\UpdateMatchPlayerRequest;
use App\Http\Resources\PlayerResource;
use App\Http\Resources\PeladaResource;
use App\Http\Resources\MatchPlayerResource;
use App\Models\Player;
use App\Models\Pelada;
use App\Models\MatchPlayer;
use App\Models\Team;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Cadastra um jogador (admin - email não obrigatório).
     */
    public function storePlayer(AdminStorePlayerRequest $request)
    {
        $player = Player::create($request->validated());
        return new PlayerResource($player);
    }

    /**
     * Atualiza um jogador.
     */
    public function updatePlayer(UpdatePlayerRequest $request, $id)
    {
        $player = Player::find($id);
        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $player->update($request->validated());
        return new PlayerResource($player);
    }

    /**
     * Remove um jogador.
     */
    public function deletePlayer($id)
    {
        $player = Player::find($id);
        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $player->delete();
        return response()->json(['message' => 'Jogador deletado com sucesso.']);
    }

    /**
     * Cadastra uma pelada.
     */
    public function storePelada(StorePeladaRequest $request)
    {
        $pelada = Pelada::create($request->validated());
        return new PeladaResource($pelada);
    }

    /**
     * Atualiza uma pelada.
     */
    public function updatePelada(UpdatePeladaRequest $request, $id)
    {
        $pelada = Pelada::find($id);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $pelada->update($request->validated());
        return new PeladaResource($pelada);
    }

    /**
     * Remove uma pelada.
     */
    public function deletePelada($id)
    {
        $pelada = Pelada::find($id);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $pelada->delete();
        return response()->json(['message' => 'Pelada deletada com sucesso.']);
    }

    /**
     * Cadastra estatísticas de um jogador em uma pelada.
     */
    public function storeMatchPlayer(StoreMatchPlayerRequest $request)
    {
        $matchPlayer = MatchPlayer::create($request->validated());
        return new MatchPlayerResource($matchPlayer);
    }

    /**
     * Atualiza estatísticas de um jogador em uma pelada.
     */
    public function updateMatchPlayer(UpdateMatchPlayerRequest $request, $id)
    {
        $matchPlayer = MatchPlayer::find($id);
        if (!$matchPlayer) {
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }

        $matchPlayer->update($request->validated());
        return new MatchPlayerResource($matchPlayer);
    }

    /**
     * Remove estatísticas de um jogador em uma pelada.
     */
    public function deleteMatchPlayer($id)
    {
        $matchPlayer = MatchPlayer::find($id);
        if (!$matchPlayer) {
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }

        $matchPlayer->delete();
        return response()->json(['message' => 'Registro removido com sucesso.']);
    }

    /**
     * Organiza times automaticamente para uma pelada.
     */
    public function organizeTeams(Request $request, $peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $request->validate([
            'player_ids' => 'required|array|min:' . ($pelada->qtd_times * $pelada->qtd_jogadores_por_time),
            'player_ids.*' => 'exists:players,id'
        ]);

        // Verifica se os jogadores já estão organizados
        $existingTeams = Team::where('pelada_id', $peladaId)->count();
        if ($existingTeams > 0) {
            return response()->json(['message' => 'Times já foram organizados para esta pelada.'], 400);
        }

        $players = Player::whereIn('id', $request->player_ids)->get();
        $goalkeepers = $players->where('position', 'goleiro');
        $fieldPlayers = $players->where('position', 'linha');

        // Verifica se há goleiros suficientes
        if ($goalkeepers->count() < $pelada->qtd_goleiros) {
            return response()->json(['message' => 'Número insuficiente de goleiros.'], 400);
        }

        // Cria os times
        $teams = [];
        for ($i = 1; $i <= $pelada->qtd_times; $i++) {
            $team = Team::create([
                'pelada_id' => $peladaId,
                'name' => 'Time ' . $i
            ]);
            $teams[] = $team;
        }

        // Converte collections para arrays para facilitar manipulação
        $goalkeepersArray = $goalkeepers->values()->all();
        $fieldPlayersArray = $fieldPlayers->values()->all();

        // Distribui goleiros
        $goalkeeperIndex = 0;
        foreach ($teams as $team) {
            $goalkeepersNeeded = min(2, count($goalkeepersArray) - $goalkeeperIndex);
            for ($g = 0; $g < $goalkeepersNeeded && $goalkeeperIndex < count($goalkeepersArray); $g++) {
                $team->players()->attach($goalkeepersArray[$goalkeeperIndex]->id);
                $goalkeeperIndex++;
            }
        }

        // Distribui jogadores de linha
        $fieldPlayerIndex = 0;
        $playersPerTeam = floor(count($fieldPlayersArray) / $pelada->qtd_times);
        
        foreach ($teams as $team) {
            for ($p = 0; $p < $playersPerTeam && $fieldPlayerIndex < count($fieldPlayersArray); $p++) {
                $team->players()->attach($fieldPlayersArray[$fieldPlayerIndex]->id);
                $fieldPlayerIndex++;
            }
        }

        // Carrega os times com seus jogadores para retorno
        $teamsWithPlayers = Team::where('pelada_id', $peladaId)->with('players')->get();

        return response()->json([
            'message' => 'Times organizados com sucesso.',
            'teams' => $teamsWithPlayers->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'players' => $team->players
                ];
            })
        ]);
    }
}
