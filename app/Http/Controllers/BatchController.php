<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Mortality;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index()
    {
        return response()->json(Batch::where('status', 'active')->orderBy('acquisition_date', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'initial_quantity' => 'required|integer|min:1',
            'acquisition_date' => 'required|date',
        ]);

        // Al crear, current_quantity es igual a initial_quantity
        $validated['current_quantity'] = $validated['initial_quantity'];

        $batch = Batch::create($validated);

        return response()->json($batch, 201);
    }

    public function show(Batch $batch)
    {
        return response()->json($batch->load('mortalities'));
    }

    public function update(Request $request, Batch $batch)
    {
        $validated = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'status'           => 'sometimes|in:active,depleted',
            'acquisition_date' => 'sometimes|date',
        ]);

        $batch->update($validated);

        return response()->json($batch);
    }

    public function destroy(Batch $batch)
    {
        $batch->delete();
        return response()->json(['message' => 'Lote eliminado correctamente.']);
    }

    /**
     * Registro de mortalidad para un lote específico.
     */
    public function registerMortality(Request $request, Batch $batch)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:' . $batch->current_quantity,
            'date'     => 'required|date',
            'reason'   => 'nullable|string|max:255',
        ]);

        $mortality = $batch->mortalities()->create($validated);

        return response()->json([
            'message'   => 'Mortalidad registrada con éxito.',
            'mortality' => $mortality,
            'batch'     => $batch->fresh(), // Devolver el lote actualizado
        ], 201);
    }
}
