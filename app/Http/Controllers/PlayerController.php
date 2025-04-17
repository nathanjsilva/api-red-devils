<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PlayerController extends Controller
{
    public function index()
    {
        $players = Player::all();
        return response()->json($players);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:players,email',
            'password' => 'required|string|min:6',
            'position' => 'required|in:linha,goleiro', // posição obrigatória
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $player = Player::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'position' => $request->position,
        ]);

        return response()->json($player, 201);
    }

    public function show($id)
    {
        $player = Player::find($id);

        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        return response()->json($player);
    }

    public function update(Request $request, $id)
    {
        $player = Player::find($id);

        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:players,email,' . $player->id,
            'password' => 'sometimes|string|min:6',
            'position' => 'sometimes|in:linha,goleiro', // posição opcional
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $player->name     = $request->get('name', $player->name);
        $player->email    = $request->get('email', $player->email);
        $player->position = $request->get('position', $player->position);

        if ($request->filled('password')) {
            $player->password = Hash::make($request->password);
        }

        $player->save();

        return response()->json($player);
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
