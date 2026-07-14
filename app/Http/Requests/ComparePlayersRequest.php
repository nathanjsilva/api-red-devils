<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComparePlayersRequest extends FormRequest
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
        return [
            'player_ids' => 'required|array|min:2|max:6',
            'player_ids.*' => 'integer|distinct|exists:players,id',
        ];
    }

    /**
     * Obtém mensagens personalizadas para erros de validação.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'player_ids.required' => 'Informe ao menos dois jogadores para comparar.',
            'player_ids.array' => 'player_ids deve ser uma lista de ids de jogadores.',
            'player_ids.min' => 'Informe ao menos dois jogadores para comparar.',
            'player_ids.max' => 'É possível comparar no máximo 6 jogadores por vez.',
            'player_ids.*.integer' => 'Cada id de jogador deve ser um número inteiro.',
            'player_ids.*.distinct' => 'Não repita o mesmo jogador na comparação.',
            'player_ids.*.exists' => 'Um ou mais jogadores informados não foram encontrados.',
        ];
    }
}
