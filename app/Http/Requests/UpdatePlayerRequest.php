<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerRequest extends FormRequest
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
        $playerId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'nickname' => ['sometimes', 'string', 'max:255', Rule::unique('players', 'nickname')->ignore($playerId)],
            'position' => 'sometimes|in:linha,goleiro',
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
            'nickname.unique' => 'Este apelido já está em uso.',
            'position.in' => 'A posição deve ser "linha" ou "goleiro".',
        ];
    }
}
