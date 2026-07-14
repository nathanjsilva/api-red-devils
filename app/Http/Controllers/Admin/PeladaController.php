<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePeladaRequest;
use App\Http\Requests\UpdatePeladaRequest;
use App\Http\Resources\PeladaResource;
use App\Models\Pelada;

class PeladaController extends Controller
{
    public function store(StorePeladaRequest $request)
    {
        $pelada = Pelada::create($request->validated());

        return new PeladaResource($pelada);
    }

    public function update(UpdatePeladaRequest $request, $id)
    {
        $pelada = Pelada::find($id);

        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $pelada->update($request->validated());

        return new PeladaResource($pelada);
    }

    public function destroy($id)
    {
        $pelada = Pelada::find($id);

        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $pelada->delete();

        return response()->json(['message' => 'Pelada deletada com sucesso.']);
    }
}
