<?php

namespace App\Http\Controllers;

use App\Models\MatchPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MatchPlayerController extends Controller
{
    // Cadastra estatísticas de um jogador em uma pelada
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_id'       => 'required|exists:players,id',
            'pelada_id'       => 'required|exists:peladas,id',
            'goals'           => 'nullable|integer|min:0',
            'assists'         => 'nullable|integer|min:0',
            'is_winner'       => 'nullable|boolean',
            'goals_conceded'  => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $matchPlayer = MatchPlayer::create($validator->validated());

        return response()->json($matchPlayer, 201);
    }

    // Atualiza estatísticas de um jogador em uma pelada
    public function update(Request $request, $id)
    {
        $matchPlayer = MatchPlayer::find($id);

        if (!$matchPlayer) {
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'goals'           => 'nullable|integer|min:0',
            'assists'         => 'nullable|integer|min:0',
            'is_winner'       => 'nullable|boolean',
            'goals_conceded'  => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $matchPlayer->update($validator->validated());

        return response()->json($matchPlayer);
    }

    // Remove o registro de estatísticas de um jogador em uma pelada
    public function destroy($id)
    {
        $matchPlayer = MatchPlayer::find($id);

        if (!$matchPlayer) {
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }

        $matchPlayer->delete();

        return response()->json(['message' => 'Registro removido com sucesso.']);
    }
}
