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
            Pelada::with('matchPlayers')->orderBy('date', 'desc')->paginate($this->perPage($request))
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

    public function byDate($date)
    {
        $peladas = Pelada::whereDate('date', $date)
            ->with('matchPlayers')
            ->get();

        if ($peladas->isEmpty()) {
            return $this->errorResponse('Nenhuma pelada encontrada para esta data.', 404);
        }

        return PeladaResource::collection($peladas);
    }
}
