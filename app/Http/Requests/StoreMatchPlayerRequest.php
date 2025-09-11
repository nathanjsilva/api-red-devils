<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMatchPlayerRequest extends FormRequest
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
            'player_id'      => 'required|exists:players,id',
            'pelada_id'      => 'required|exists:peladas,id',
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
            'player_id.required' => 'O ID do jogador é obrigatório.',
            'player_id.exists'   => 'Jogador não encontrado.',
            'pelada_id.required' => 'O ID da pelada é obrigatório.',
            'pelada_id.exists'   => 'Pelada não encontrada.',
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

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Verificar se o jogador já está na pelada
            if ($this->player_id && $this->pelada_id) {
                $existing = \App\Models\MatchPlayer::where('player_id', $this->player_id)
                                                  ->where('pelada_id', $this->pelada_id)
                                                  ->exists();
                
                if ($existing) {
                    $validator->errors()->add('player_id', 'Este jogador já está registrado nesta pelada.');
                }
            }

            // Verificar se goleiro tem goals_conceded
            if ($this->player_id) {
                $player = \App\Models\Player::find($this->player_id);
                if ($player && $player->position === 'goleiro' && $this->goals_conceded === null) {
                    $validator->errors()->add('goals_conceded', 'Goleiros devem ter o campo "gols sofridos" preenchido.');
                }
            }
        });
    }
}
