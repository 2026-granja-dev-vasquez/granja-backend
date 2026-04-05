<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Mortality;
use App\Models\BatchAdjustment;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index()
    {
        return response()->json(Batch::orderBy('status')->orderBy('acquisition_date', 'desc')->get());
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
        return response()->json($batch->load(['mortalities', 'adjustments']));
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

    /**
     * Registro de ajuste de inventario (aves) para un lote específico.
     */
    public function registerAdjustment(Request $request, Batch $batch)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|not_in:0', // Puede ser positivo o negativo
            'date'     => 'required|date',
            'reason'   => 'required|string|max:255',
        ]);

        // Si es negativo (reducción), no puede ser mayor que lo que hay vivo
        if ($validated['quantity'] < 0 && abs($validated['quantity']) > $batch->current_quantity) {
            return response()->json(['message' => 'No puedes ajustar más aves de las que hay vivas.'], 422);
        }

        $adjustment = $batch->adjustments()->create($validated);

        return response()->json([
            'message'    => 'Ajuste de lote registrado con éxito.',
            'adjustment' => $adjustment,
            'batch'      => $batch->fresh(),
        ], 201);
    }
}
