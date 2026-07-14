<?php

namespace App\Http\Requests;

use App\Models\Pelada;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePeladaRequest extends FormRequest
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
            'date' => 'sometimes|date',
            'division' => 'sometimes|in:quinta,sabado',
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
            'division.in' => 'Divisão deve ser: quinta ou sabado.',
            'qtd_times.min' => 'Deve ter pelo menos 2 times.',
            'qtd_times.max' => 'Máximo de 10 times.',
            'qtd_jogadores_por_time.min' => 'Mínimo de 5 jogadores por time.',
            'qtd_jogadores_por_time.max' => 'Máximo de 15 jogadores por time.',
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
            if (! $this->has('date') && ! $this->has('division')) {
                return;
            }

            $pelada = Pelada::find($this->route('id'));
            if (! $pelada) {
                return;
            }

            $effectiveDate = $this->input('date', $pelada->date);
            $effectiveDivision = $this->input('division', $pelada->division);

            if (! isset(self::DIVISION_WEEKDAYS[$effectiveDivision])) {
                return;
            }

            $weekday = (int) date('N', strtotime($effectiveDate));

            if ($weekday !== self::DIVISION_WEEKDAYS[$effectiveDivision]) {
                $validator->errors()->add('division', 'A data informada não corresponde ao dia da semana da divisão "'.$effectiveDivision.'".');
            }
        });
    }
}
