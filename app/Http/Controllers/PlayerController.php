<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Http\Requests\StorePlayerRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Http\Resources\PlayerResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PlayerController extends Controller
{
    public function index()
    {
        $players = Player::all();
        return PlayerResource::collection($players);
    }

    public function store(StorePlayerRequest $request)
    {
        $player = Player::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'position' => $request->position,
            'phone'    => $request->phone,
            'nickname' => $request->nickname,
        ]);

        return new PlayerResource($player);
    }

    public function show($id)
    {
        $player = Player::find($id);

        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        return new PlayerResource($player);
    }

    public function update(UpdatePlayerRequest $request, $id)
    {
        $player = Player::find($id);

        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $player->name     = $request->get('name', $player->name);
        $player->email    = $request->get('email', $player->email);
        $player->position = $request->get('position', $player->position);
        $player->phone    = $request->get('phone', $player->phone);
        $player->nickname = $request->get('nickname', $player->nickname);

        if ($request->filled('password')) {
            $player->password = Hash::make($request->password);
        }

        $player->save();

        return new PlayerResource($player);
    }

    public function destroy($id)
    {
        $player = Player::find($id);

        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $player->delete();

        return response()->json(['message' => 'Jogador deletado com sucesso.']);
    }
}
