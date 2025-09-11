<?php

namespace App\Http\Controllers;

use App\Models\Pelada;
use App\Http\Requests\StorePeladaRequest;
use App\Http\Requests\UpdatePeladaRequest;
use App\Http\Resources\PeladaResource;
use Illuminate\Http\Request;

class PeladaController extends Controller
{
    public function index()
    {
        $peladas = Pelada::with('players')->get();
        return PeladaResource::collection($peladas);
    }

    public function store(StorePeladaRequest $request)
    {
        $pelada = Pelada::create([
            'date' => $request->date,
        ]);

        return new PeladaResource($pelada);
    }

    public function show($id)
    {
        $pelada = Pelada::with('players')->find($id);

        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        return new PeladaResource($pelada);
    }

    public function update(UpdatePeladaRequest $request, $id)
    {
        $pelada = Pelada::find($id);

        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $pelada->update($request->only('date'));

        return new PeladaResource($pelada);
    }

    public function destroy($id)
    {
        $pelada = Pelada::find($id);

        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $pelada->delete();

        return response()->json(['message' => 'Pelada deletada com sucesso.']);
    }
}
