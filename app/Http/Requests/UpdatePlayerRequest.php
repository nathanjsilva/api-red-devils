<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $playerId = $this->route('id');
        
        return [
            'name'     => 'sometimes|string|max:255|unique:players,name,' . $playerId,
            'email'    => ['sometimes', 'email', Rule::unique('players')->ignore($playerId)],
            'password' => 'sometimes|string|min:6',
            'position' => 'sometimes|in:linha,goleiro',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique'       => 'O nome já está em uso.',
            'email.email'       => 'O e-mail deve ter um formato válido.',
            'email.unique'      => 'Este e-mail já está cadastrado.',
            'password.min'      => 'A senha deve ter pelo menos 6 caracteres.',
            'position.in'       => 'A posição deve ser "linha" ou "goleiro".',
        ];
    }
}
