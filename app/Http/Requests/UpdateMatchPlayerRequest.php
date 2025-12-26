<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchPlayerRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Se player_id e pelada_id estão sendo enviados, validar que existem
        // Isso permite usar updateOrCreate quando necessário
        $rules = [
            'player_id'      => 'sometimes|exists:players,id',
            'pelada_id'      => 'sometimes|exists:peladas,id',
            'goals'          => 'nullable|integer|min:0',
            'assists'        => 'nullable|integer|min:0',
            'is_winner'      => 'nullable|boolean',
            'result'         => 'nullable|in:win,loss,draw',
            'goals_conceded' => 'nullable|integer|min:0',
        ];

        return $rules;
    }

    /**
     * Obtém mensagens personalizadas para erros de validação.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'player_id.exists'      => 'Jogador não encontrado.',
            'pelada_id.exists'      => 'Pelada não encontrada.',
            'goals.integer'         => 'Gols deve ser um número inteiro.',
            'goals.min'             => 'Gols não pode ser negativo.',
            'assists.integer'       => 'Assistências deve ser um número inteiro.',
            'assists.min'           => 'Assistências não pode ser negativo.',
            'is_winner.boolean'     => 'Status de vencedor deve ser verdadeiro ou falso.',
            'result.in'             => 'Resultado deve ser: win, loss ou draw.',
            'goals_conceded.integer' => 'Gols sofridos deve ser um número inteiro.',
            'goals_conceded.min'     => 'Gols sofridos não pode ser negativo.',
        ];
    }

    /**
     * Configura a instância do validador.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $playerId = $this->player_id ?? $this->route('playerId');
            $peladaId = $this->pelada_id ?? $this->route('peladaId');
            
            // Se player_id e pelada_id foram enviados (no body ou na URL), verificar validações específicas
            if ($playerId && $peladaId) {
                // Verificar se goleiro tem goals_conceded
                $player = \App\Models\Player::find($playerId);
                if ($player && $player->position === 'goleiro') {
                    if ($this->has('goals_conceded') && $this->goals_conceded === null) {
                        // Verificar se já existe um registro com goals_conceded
                        $existing = \App\Models\MatchPlayer::where('player_id', $playerId)
                                                           ->where('pelada_id', $peladaId)
                                                           ->first();
                        if (!$existing || $existing->goals_conceded === null) {
                            $validator->errors()->add('goals_conceded', 'Goleiros devem ter o campo "gols sofridos" preenchido.');
                        }
                    }
                }
            } else {
                // Se não foram enviados, usar o ID da rota (comportamento antigo)
                $matchPlayerId = $this->route('id');
                
                if ($matchPlayerId) {
                    $matchPlayer = \App\Models\MatchPlayer::find($matchPlayerId);
                    
                    if ($matchPlayer && $matchPlayer->player) {
                        $player = $matchPlayer->player;
                        if ($player->position === 'goleiro') {
                            if ($this->has('goals_conceded') && $this->goals_conceded === null && $matchPlayer->goals_conceded === null) {
                                $validator->errors()->add('goals_conceded', 'Goleiros devem ter o campo "gols sofridos" preenchido.');
                            }
                        }
                    }
                }
            }
        });
    }
}
