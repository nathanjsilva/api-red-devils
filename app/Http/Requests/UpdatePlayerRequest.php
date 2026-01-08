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
            'name'     => 'sometimes|string|max:255|unique:players,name,' . $playerId,
            'email'    => ['sometimes', 'email', Rule::unique('players')->ignore($playerId)],
            'password' => 'sometimes|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'position' => 'sometimes|in:linha,goleiro',
            'phone'    => ['sometimes', 'string', Rule::unique('players')->ignore($playerId)],
            'nickname' => 'sometimes|string|max:255|unique:players,nickname,' . $playerId,
            'is_admin' => 'sometimes|boolean',
            'user_id'  => ['sometimes', 'nullable', 'exists:users,id', Rule::unique('players')->ignore($playerId)],
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
            'name.unique'       => 'O nome já está em uso.',
            'email.email'       => 'O e-mail deve ter um formato válido.',
            'email.unique'      => 'Este e-mail já está cadastrado.',
            'password.min'      => 'A senha deve ter pelo menos 8 caracteres.',
            'password.regex'    => 'A senha deve conter pelo menos: 1 letra minúscula, 1 maiúscula, 1 número e 1 caractere especial.',
            'position.in'       => 'A posição deve ser "linha" ou "goleiro".',
            'phone.unique'      => 'Este telefone já está cadastrado.',
            'nickname.unique'   => 'Este apelido já está em uso.',
        ];
    }
}
