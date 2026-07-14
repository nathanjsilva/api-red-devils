<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePeladaRequest extends FormRequest
{
    /**
     * Dia da semana (formato ISO-8601 de `date('N')`, 1=segunda...7=domingo)
     * esperado para cada divisão.
     */
    private const DIVISION_WEEKDAYS = [
        'quinta' => 4,
        'sabado' => 6,
    ];

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
            'date' => 'required|date|after_or_equal:today',
            'division' => 'required|in:quinta,sabado',
            'location' => 'required|string|max:255',
            'qtd_times' => 'required|integer|min:2|max:10',
            'qtd_jogadores_por_time' => 'required|integer|min:5|max:15',
            'qtd_goleiros' => 'required|integer|min:2|max:10',
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
            'date.required' => 'A data é obrigatória.',
            'date.date' => 'A data deve ter um formato válido.',
            'date.after_or_equal' => 'A data não pode ser anterior a hoje.',
            'division.required' => 'A divisão é obrigatória.',
            'division.in' => 'Divisão deve ser: quinta ou sabado.',
            'location.required' => 'O local é obrigatório.',
            'qtd_times.required' => 'A quantidade de times é obrigatória.',
            'qtd_times.min' => 'Deve ter pelo menos 2 times.',
            'qtd_times.max' => 'Máximo de 10 times.',
            'qtd_jogadores_por_time.required' => 'A quantidade de jogadores por time é obrigatória.',
            'qtd_jogadores_por_time.min' => 'Mínimo de 5 jogadores por time.',
            'qtd_jogadores_por_time.max' => 'Máximo de 15 jogadores por time.',
            'qtd_goleiros.required' => 'A quantidade de goleiros é obrigatória.',
            'qtd_goleiros.min' => 'Mínimo de 2 goleiros.',
            'qtd_goleiros.max' => 'Máximo de 10 goleiros.',
        ];
    }

    /**
     * Configura a instância do validador.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (! $this->date || ! $this->division || ! isset(self::DIVISION_WEEKDAYS[$this->division])) {
                return;
            }

            $weekday = (int) date('N', strtotime($this->date));

            if ($weekday !== self::DIVISION_WEEKDAYS[$this->division]) {
                $validator->errors()->add('division', 'A data informada não corresponde ao dia da semana da divisão "'.$this->division.'".');
            }
        });
    }
}
