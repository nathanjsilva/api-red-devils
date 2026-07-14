<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminStorePlayerRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Http\Resources\PlayerResource;
use App\Models\Player;

class PlayerController extends Controller
{
    public function store(AdminStorePlayerRequest $request)
    {
        $player = Player::create($request->validated());

        return new PlayerResource($player);
    }

    public function update(UpdatePlayerRequest $request, $id)
    {
        $player = Player::find($id);

        if (! $player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        $player->update($request->validated());

        return new PlayerResource($player);
    }

    public function destroy($id)
    {
        $player = Player::find($id);

        if (! $player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        $player->delete();

        return response()->json(['message' => 'Jogador deletado com sucesso.']);
    }
}
