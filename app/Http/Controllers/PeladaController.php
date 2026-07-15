<?php

namespace App\Http\Controllers;

use App\Http\Resources\PeladaResource;
use App\Models\Pelada;
use Illuminate\Http\Request;

class PeladaController extends Controller
{
    public function index(Request $request)
    {
        return PeladaResource::collection(
            Pelada::with('matchPlayers')
                ->when($this->divisionFromRequest($request), fn ($query, $division) => $query->where('division', $division))
                ->orderBy('date', 'desc')
                ->paginate($this->perPage($request))
        );
    }

    public function show($id)
    {
        $pelada = Pelada::with('matchPlayers')->find($id);

        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        return new PeladaResource($pelada);
    }

    public function byDate(Request $request, $date)
    {
        $peladas = Pelada::whereDate('date', $date)
            ->when($this->divisionFromRequest($request), fn ($query, $division) => $query->where('division', $division))
            ->with('matchPlayers')
            ->get();

        if ($peladas->isEmpty()) {
            return $this->errorResponse('Nenhuma pelada encontrada para esta data.', 404);
        }

        return PeladaResource::collection($peladas);
    }

    /**
     * `division` opcional (quinta|sabado) para filtrar as listagens. Valor
     * fora desse conjunto é ignorado silenciosamente, mesmo padrão tolerante
     * usado nos filtros de `StatisticsController`.
     */
    private function divisionFromRequest(Request $request): ?string
    {
        $division = $request->query('division');

        return in_array($division, ['quinta', 'sabado'], true) ? $division : null;
    }
}
