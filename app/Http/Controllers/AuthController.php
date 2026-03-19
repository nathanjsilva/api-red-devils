<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Credenciais inválidas.', 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $authData = (object) [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ];

        return new AuthResource($authData);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Não autenticado.', 401);
        }

        return new UserResource($user);
    }
}
