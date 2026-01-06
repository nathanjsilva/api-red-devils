<?php

namespace App\Http\Controllers;

use App\Models\MatchPlayer;
use App\Http\Requests\StoreMatchPlayerRequest;
use App\Http\Requests\UpdateMatchPlayerRequest;
use App\Http\Resources\MatchPlayerResource;
use Illuminate\Http\Request;

class MatchPlayerController extends Controller
{
    public function store(StoreMatchPlayerRequest $request)
    {
        $matchPlayer = MatchPlayer::create($request->validated());
        return new MatchPlayerResource($matchPlayer);
    }

    public function update(UpdateMatchPlayerRequest $request, $id)
    {
        $matchPlayer = MatchPlayer::find($id);

        if (!$matchPlayer) {
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }

        $matchPlayer->update($request->validated());
        return new MatchPlayerResource($matchPlayer);
    }

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
