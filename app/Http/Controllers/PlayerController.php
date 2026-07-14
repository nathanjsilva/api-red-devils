<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        return PlayerResource::collection(
            Player::orderBy('name')->paginate($this->perPage($request))
        );
    }

    public function show($id)
    {
        $player = Player::find($id);

        if (! $player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        return new PlayerResource($player);
    }
}
