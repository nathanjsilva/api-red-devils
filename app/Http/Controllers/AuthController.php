<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Player;
use App\Http\Resources\AuthResource;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $player = Player::where('email', $request->email)->first();

        if (!$player || !Hash::check($request->password, $player->password)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        $token = $player->createToken('auth_token')->plainTextToken;

        $authData = (object) [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'player' => $player,
        ];

        return new AuthResource($authData);
    }
}
