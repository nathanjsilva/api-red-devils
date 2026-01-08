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
use App\Http\Resources\UserResource;
use App\Models\Player;
use App\Models\User;
use App\Models\Pelada;
use App\Models\MatchPlayer;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function setupFirstAdmin(Request $request)
    {
        $adminExists = User::where('profile', 'admin')->exists();
        if ($adminExists) {
            return response()->json([
                'message' => 'Já existem administradores no sistema. Use as rotas de admin para gerenciar permissões.'
            ], 400);
        }

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'position' => 'required|in:linha,goleiro',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'position' => $request->position,
            'profile' => 'admin',
        ]);

        return response()->json([
            'message' => 'Primeiro administrador criado com sucesso!',
            'user' => new UserResource($user)
        ], 201);
    }
    public function storePlayer(AdminStorePlayerRequest $request)
    {
        $validated = $request->validated();
        
        if (isset($validated['user_id'])) {
            $user = User::find($validated['user_id']);
            if (!$user) {
                return response()->json(['message' => 'Usuário não encontrado.'], 404);
            }
            
            if ($user->player) {
                return response()->json(['message' => 'Este usuário já está vinculado a um jogador.'], 400);
            }
        }
        
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else if (!isset($validated['user_id'])) {
            $validated['password'] = Hash::make(Str::random(16));
        }
        
        $player = Player::create($validated);
        
        if ($player->user_id) {
            $player->load('user');
        }
        
        return new PlayerResource($player);
    }

    public function updatePlayer(UpdatePlayerRequest $request, $id)
    {
        $player = Player::find($id);
        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $validated = $request->validated();
        
        if (isset($validated['user_id']) && $validated['user_id'] !== $player->user_id) {
            $user = User::find($validated['user_id']);
            if (!$user) {
                return response()->json(['message' => 'Usuário não encontrado.'], 404);
            }
            
            if ($user->player && $user->player->id !== $player->id) {
                return response()->json(['message' => 'Este usuário já está vinculado a outro jogador.'], 400);
            }
        }
        
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $player->update($validated);
        
        if ($player->user_id) {
            $player->load('user');
        }
        
        return new PlayerResource($player);
    }

    public function deletePlayer($id)
    {
        $player = Player::find($id);
        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $player->delete();
        return response()->json(['message' => 'Jogador deletado com sucesso.']);
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
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $pelada->update($request->validated());
        return new PeladaResource($pelada);
    }

    public function deletePelada($id)
    {
        $pelada = Pelada::find($id);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
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
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }

        $matchPlayer->update($request->validated());
        $matchPlayer->refresh();
        $matchPlayer->load(['player', 'pelada']);
        
        return new MatchPlayerResource($matchPlayer);
    }

    public function updateMatchPlayerByPlayerAndPelada(UpdateMatchPlayerRequest $request, $peladaId, $playerId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $player = Player::find($playerId);
        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $validated = $request->validated();
        unset($validated['player_id'], $validated['pelada_id']);
        
        $matchPlayer = MatchPlayer::updateOrCreate(
            [
                'player_id' => $playerId,
                'pelada_id' => $peladaId
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
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }

        $matchPlayer->delete();
        return response()->json(['message' => 'Registro removido com sucesso.']);
    }

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

        $existingTeams = Team::where('pelada_id', $peladaId)->get();
        if ($existingTeams->count() > 0) {
            foreach ($existingTeams as $team) {
                $team->players()->detach();
                $team->delete();
            }
        }

        // Filtra apenas players que estão vinculados a usuários (user_id não é null)
        $players = Player::whereIn('id', $request->player_ids)
            ->whereNotNull('user_id')
            ->get();
        $goalkeepers = $players->where('position', 'goleiro');
        $fieldPlayers = $players->where('position', 'linha');

        if ($goalkeepers->count() < $pelada->qtd_goleiros) {
            return response()->json(['message' => 'Número insuficiente de goleiros.'], 400);
        }

        $teams = [];
        for ($i = 1; $i <= $pelada->qtd_times; $i++) {
            $team = Team::create([
                'pelada_id' => $peladaId,
                'name' => 'Time ' . $i
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
                    'players' => $team->players
                ];
            })
        ]);
    }

    public function makeAdmin($id)
    {
        $player = Player::find($id);
        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $player->update(['is_admin' => true]);
        
        return response()->json([
            'message' => 'Jogador transformado em admin com sucesso.',
            'player' => new PlayerResource($player)
        ]);
    }

    public function removeAdmin($id)
    {
        $player = Player::find($id);
        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $adminCount = Player::where('is_admin', true)->count();
        if ($adminCount <= 1 && $player->is_admin) {
            return response()->json([
                'message' => 'Não é possível remover o último administrador do sistema.'
            ], 400);
        }

        $player->update(['is_admin' => false]);
        
        return response()->json([
            'message' => 'Permissões de admin removidas com sucesso.',
            'player' => new PlayerResource($player)
        ]);
    }

    public function listAvailableUsers(Request $request)
    {
        $users = User::with('player')->get();

        return UserResource::collection($users);
    }
}
