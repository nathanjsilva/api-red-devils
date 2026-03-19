<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerResource;
use App\Models\Player;

class PlayerController extends Controller
{
    public function index()
    {
        return PlayerResource::collection(Player::orderBy('name')->get());
    }

    public function show($id)
    {
        $player = Player::find($id);

        if (!$player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        return new PlayerResource($player);
    }
}
