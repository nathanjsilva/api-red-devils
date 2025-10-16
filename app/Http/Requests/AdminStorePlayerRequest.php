<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminStorePlayerRequest extends FormRequest
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
            'name'     => 'required|string|max:255|unique:players,name',
            'email'    => 'nullable|email|unique:players,email',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'position' => 'required|in:linha,goleiro',
            'phone'    => 'required|string|unique:players,phone',
            'nickname' => 'required|string|max:255|unique:players,nickname',
            'is_admin' => 'boolean',
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
            'name.required'     => 'O nome é obrigatório.',
            'name.unique'       => 'O nome já está em uso.',
            'email.email'       => 'O e-mail deve ter um formato válido.',
            'email.unique'      => 'Este e-mail já está cadastrado.',
            'password.required' => 'A senha é obrigatória.',
            'password.min'      => 'A senha deve ter pelo menos 8 caracteres.',
            'password.regex'    => 'A senha deve conter pelo menos: 1 letra minúscula, 1 maiúscula, 1 número e 1 caractere especial.',
            'position.required' => 'A posição é obrigatória.',
            'position.in'       => 'A posição deve ser "linha" ou "goleiro".',
            'phone.required'    => 'O telefone é obrigatório.',
            'phone.unique'      => 'Este telefone já está cadastrado.',
            'nickname.required' => 'O apelido é obrigatório.',
            'nickname.unique'   => 'Este apelido já está em uso.',
        ];
    }
}

