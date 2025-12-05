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
        return [
            'goals'          => 'nullable|integer|min:0',
            'assists'        => 'nullable|integer|min:0',
            'is_winner'      => 'nullable|boolean',
            'result'         => 'nullable|in:win,loss,draw',
            'goals_conceded' => 'nullable|integer|min:0',
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
            'goals.integer'      => 'Gols deve ser um número inteiro.',
            'goals.min'          => 'Gols não pode ser negativo.',
            'assists.integer'    => 'Assistências deve ser um número inteiro.',
            'assists.min'        => 'Assistências não pode ser negativo.',
            'is_winner.boolean'  => 'Status de vencedor deve ser verdadeiro ou falso.',
            'result.in'          => 'Resultado deve ser: win, loss ou draw.',
            'goals_conceded.integer' => 'Gols sofridos deve ser um número inteiro.',
            'goals_conceded.min'     => 'Gols sofridos não pode ser negativo.',
        ];
    }
}
