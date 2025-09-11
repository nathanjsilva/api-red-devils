<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Http\Requests\StorePlayerRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Http\Resources\PlayerResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Players",
 *     description="Operações relacionadas aos jogadores"
 * )
 */
class PlayerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/players",
     *     summary="Listar todos os jogadores",
     *     tags={"Players"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de jogadores retornada com sucesso",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Player")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autorizado"
     *     )
     * )
     */
    public function index()
    {
        $players = Player::all();
        return PlayerResource::collection($players);
    }

    /**
     * @OA\Post(
     *     path="/players",
     *     summary="Criar novo jogador",
     *     tags={"Players"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","position"},
     *             @OA\Property(property="name", type="string", example="João Silva"),
     *             @OA\Property(property="email", type="string", format="email", example="joao@test.com"),
     *             @OA\Property(property="password", type="string", format="password", example="123456"),
     *             @OA\Property(property="position", type="string", enum={"linha","goleiro"}, example="linha")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Jogador criado com sucesso",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados de validação inválidos"
     *     )
     * )
     */
    public function store(StorePlayerRequest $request)
    {
        $player = Player::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'position' => $request->position,
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
