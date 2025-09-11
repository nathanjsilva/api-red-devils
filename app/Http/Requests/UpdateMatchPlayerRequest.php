<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchPlayerRequest extends FormRequest
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
            'goals'          => 'nullable|integer|min:0|max:20',
            'assists'        => 'nullable|integer|min:0|max:20',
            'is_winner'      => 'nullable|boolean',
            'goals_conceded' => 'nullable|integer|min:0|max:20',
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
            'goals.integer'      => 'Gols deve ser um número inteiro.',
            'goals.min'          => 'Gols não pode ser negativo.',
            'goals.max'          => 'Gols não pode ser maior que 20.',
            'assists.integer'    => 'Assistências deve ser um número inteiro.',
            'assists.min'        => 'Assistências não pode ser negativo.',
            'assists.max'        => 'Assistências não pode ser maior que 20.',
            'is_winner.boolean'  => 'Status de vencedor deve ser verdadeiro ou falso.',
            'goals_conceded.integer' => 'Gols sofridos deve ser um número inteiro.',
            'goals_conceded.min'     => 'Gols sofridos não pode ser negativo.',
            'goals_conceded.max'     => 'Gols sofridos não pode ser maior que 20.',
        ];
    }
}
