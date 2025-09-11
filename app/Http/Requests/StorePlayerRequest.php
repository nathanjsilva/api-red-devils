<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlayerRequest extends FormRequest
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
        return [
            'name'     => 'required|string|max:255|unique:players,name',
            'email'    => 'required|email|unique:players,email',
            'password' => 'required|string|min:6',
            'position' => 'required|in:linha,goleiro',
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
            'name.required'     => 'O nome é obrigatório.',
            'name.unique'       => 'O nome já está em uso.',
            'email.required'    => 'O e-mail é obrigatório.',
            'email.email'       => 'O e-mail deve ter um formato válido.',
            'email.unique'      => 'Este e-mail já está cadastrado.',
            'password.required' => 'A senha é obrigatória.',
            'password.min'      => 'A senha deve ter pelo menos 6 caracteres.',
            'position.required' => 'A posição é obrigatória.',
            'position.in'       => 'A posição deve ser "linha" ou "goleiro".',
        ];
    }
}
