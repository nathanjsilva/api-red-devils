<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePeladaRequest extends FormRequest
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
            'date' => 'sometimes|date|after_or_equal:today',
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
            'date.date'             => 'A data deve ter um formato válido.',
            'date.after_or_equal'   => 'A data não pode ser anterior a hoje.',
        ];
    }
}
