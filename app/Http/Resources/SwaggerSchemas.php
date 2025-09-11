<?php

namespace App\Http\Resources;

/**
 * @OA\Schema(
 *     schema="Player",
 *     type="object",
 *     title="Player",
 *     description="Modelo de jogador",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="João Silva"),
 *     @OA\Property(property="email", type="string", format="email", example="joao@test.com"),
 *     @OA\Property(property="position", type="string", enum={"linha","goleiro"}, example="linha"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01 12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01 12:00:00")
 * )
 * 
 * @OA\Schema(
 *     schema="Pelada",
 *     type="object",
 *     title="Pelada",
 *     description="Modelo de pelada",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="date", type="string", format="date", example="2024-12-25"),
 *     @OA\Property(property="players_count", type="integer", example=10),
 *     @OA\Property(property="players", type="array", @OA\Items(ref="#/components/schemas/Player")),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01 12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01 12:00:00")
 * )
 * 
 * @OA\Schema(
 *     schema="MatchPlayer",
 *     type="object",
 *     title="MatchPlayer",
 *     description="Modelo de estatísticas de jogador em pelada",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="player", ref="#/components/schemas/Player"),
 *     @OA\Property(property="pelada", ref="#/components/schemas/Pelada"),
 *     @OA\Property(property="goals", type="integer", example=2),
 *     @OA\Property(property="assists", type="integer", example=1),
 *     @OA\Property(property="goals_conceded", type="integer", nullable=true, example=1),
 *     @OA\Property(property="is_winner", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01 12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01 12:00:00")
 * )
 * 
 * @OA\Schema(
 *     schema="AuthResponse",
 *     type="object",
 *     title="AuthResponse",
 *     description="Resposta de autenticação",
 *     @OA\Property(property="access_token", type="string", example="1|abcdef123456"),
 *     @OA\Property(property="token_type", type="string", example="Bearer"),
 *     @OA\Property(property="player", ref="#/components/schemas/Player")
 * )
 * 
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     title="Error",
 *     description="Resposta de erro",
 *     @OA\Property(property="message", type="string", example="Mensagem de erro"),
 *     @OA\Property(property="errors", type="object", example={"field": ["Erro de validação"]})
 * )
 */
class SwaggerSchemas
{
    // Esta classe serve apenas para documentação Swagger
}
