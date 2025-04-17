<?php

namespace App\Http\Controllers;

use App\Models\Pelada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PeladaController extends Controller
{
    public function index()
    {
        $peladas = Pelada::with('players')->get();
        return response()->json($peladas);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pelada = Pelada::create([
            'date' => $request->date,
        ]);

        return response()->json($pelada, 201);
    }

    public function show($id)
    {
        $pelada = Pelada::with('players')->find($id);

        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        return response()->json($pelada);
    }

    public function update(Request $request, $id)
    {
        $pelada = Pelada::find($id);

        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pelada->update($request->only('date'));

        return response()->json($pelada);
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
