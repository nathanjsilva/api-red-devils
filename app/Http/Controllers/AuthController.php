<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\Player;
use App\Http\Resources\AuthResource;
use App\Http\Resources\PlayerResource;
use Carbon\Carbon;

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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(Request $request)
    {
        $player = $request->user();
        
        if (!$player) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        return new PlayerResource($player);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $player = Player::where('email', $request->email)->first();

        if (!$player) {
            return response()->json([
                'message' => 'Se o email existir, um link de recuperação será enviado.'
            ], 200);
        }

        $token = Str::random(64);
        
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );

        $resetUrl = env('FRONTEND_URL', env('APP_URL')) . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);

        try {
            Mail::send('emails.password-reset', [
                'token' => $token,
                'player' => $player,
                'resetUrl' => $resetUrl
            ], function ($message) use ($player) {
                $message->to($player->email, $player->name);
                $message->subject('Recuperação de Senha - Red Devils');
            });
        } catch (\Exception $e) {
            \Log::error('Erro ao enviar email de recuperação de senha: ' . $e->getMessage());
            
            if (env('APP_ENV') === 'local' || env('APP_DEBUG')) {
                return response()->json([
                    'message' => 'Email não pôde ser enviado. Use o token abaixo para desenvolvimento.',
                    'reset_token' => $token,
                    'reset_url' => $resetUrl,
                    'error' => 'Email não configurado ou erro no envio'
                ], 200);
            }
        }

        $response = [
            'message' => 'Se o email existir, um link de recuperação será enviado.'
        ];

        if (env('APP_ENV') === 'local' || env('APP_DEBUG')) {
            $response['reset_token'] = $token;
            $response['reset_url'] = $resetUrl;
            $response['debug'] = 'Modo desenvolvimento: token visível';
        }

        return response()->json($response, 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:players,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
        ], [
            'password.regex' => 'A senha deve conter pelo menos uma letra maiúscula, uma minúscula, um número e um caractere especial.',
        ]);

        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'message' => 'Token de recuperação não encontrado ou expirado.'
            ], 400);
        }

        if (!Hash::check($request->token, $passwordReset->token)) {
            return response()->json([
                'message' => 'Token inválido.'
            ], 400);
        }

        $tokenAge = Carbon::parse($passwordReset->created_at)->diffInMinutes(now());
        if ($tokenAge > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Token expirado. Solicite um novo link de recuperação.'
            ], 400);
        }

        $player = Player::where('email', $request->email)->first();
        $player->password = Hash::make($request->password);
        $player->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        $player->tokens()->delete();

        return response()->json([
            'message' => 'Senha redefinida com sucesso.'
        ], 200);
    }
}
