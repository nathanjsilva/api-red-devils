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
            'date' => 'sometimes|date',
            'location' => 'sometimes|string|max:255',
            'qtd_times' => 'sometimes|integer|min:2|max:10',
            'qtd_jogadores_por_time' => 'sometimes|integer|min:5|max:15',
            'qtd_goleiros' => 'sometimes|integer|min:2|max:10',
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
            'date.date' => 'A data deve ter um formato válido.',
            'qtd_times.min' => 'Deve ter pelo menos 2 times.',
            'qtd_times.max' => 'Máximo de 10 times.',
            'qtd_jogadores_por_time.min' => 'Mínimo de 5 jogadores por time.',
            'qtd_jogadores_por_time.max' => 'Máximo de 15 jogadores por time.',
            'qtd_goleiros.min' => 'Mínimo de 2 goleiros.',
            'qtd_goleiros.max' => 'Máximo de 10 goleiros.',
        ];
    }
}
