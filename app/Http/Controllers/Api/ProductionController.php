<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Production;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductionController extends Controller
{
    /**
     * Get recent sorted productions.
     */
    public function index(Request $request)
    {
        $query = Production::with('productSize')->orderBy('date', 'desc');

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        } elseif ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        return response()->json($query->get());
    }

    /**
     * Store a sorted production entry (Results of cleaning/sorting).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_size_id' => 'nullable|exists:product_sizes,id',
            'useful_quantity' => 'required|integer|min:0',
            'damaged_quantity' => 'nullable|integer|min:0',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $production = Production::create($request->all());

        // Actualizar Stock de Inventario automáticamente si no es un registro global de quebrados
        if ($production->product_size_id && $production->useful_quantity > 0) {
            $inventory = \App\Models\Inventory::firstOrCreate(
                ['product_size_id' => $production->product_size_id],
                ['units_available' => 0]
            );
            
            $inventory->increment('units_available', $production->useful_quantity);
        }

        return response()->json([
            'message' => 'Clasificación registrada y stock actualizado',
            'data' => $production->load('productSize')
        ], 201);
    }

    /**
     * Get daily production reports for a given range.
     */
    public function summary(Request $request)
    {
        // 1. Determine Range
        $startDateStr = $request->start_date;
        $endDateStr = $request->end_date ?? $request->date;

        if (!$startDateStr && !$endDateStr) {
            $endDate = Carbon::now()->endOfDay();
            $startDate = Carbon::now()->subDays(2)->startOfDay();
        } else {
            $startDate = $startDateStr ? Carbon::parse($startDateStr)->startOfDay() : Carbon::parse($endDateStr)->startOfDay();
            $endDate = Carbon::parse($endDateStr)->endOfDay();
        }

        $query = Production::with('productSize')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc');

        $productions = $query->get();

        // Group by date
        $grouped = $productions->groupBy(function($item) {
             return $item->date->toDateString();
        });

        $finalReports = [];
        $currentDate = $endDate->copy();

        while ($currentDate->greaterThanOrEqualTo($startDate)) {
            $dateStr = $currentDate->toDateString();
            
            // Calcular saldo pendiente al cierre de ese día específico
            $totalCollUpToDate = \App\Models\BatchCollection::whereDate('date', '<=', $dateStr)->sum('quantity');
            $totalSortUpToDate = Production::whereDate('date', '<=', $dateStr)->sum(DB::raw('useful_quantity + damaged_quantity'));
            $pendingAtClose = max(0, $totalCollUpToDate - $totalSortUpToDate);

            if ($grouped->has($dateStr)) {
                $items = $grouped->get($dateStr);
                $totalDamagedValue = (int)$items->sum('damaged_quantity');
                
                $bySize = $items->filter(fn($i) => $i->product_size_id !== null)
                    ->groupBy('product_size_id')->map(function($sizeItems) {
                    $first = $sizeItems->first();
                    $units = (int)$sizeItems->sum('useful_quantity');
                    $cartons = (int)floor($units / 30);
                    $leftover = $units % 30;

                    return [
                        'product_size_id' => $first->product_size_id,
                        'product_size' => $first->productSize->name,
                        'total_units' => $units,
                        'cartons' => $cartons,
                        'leftover_units' => $leftover,
                        'formatted' => "{$cartons} cartones y {$leftover} huevos"
                    ];
                })->values();

                $finalReports[] = [
                    'date' => $dateStr,
                    'total_damaged' => $totalDamagedValue,
                    'total_pending' => (int)$pendingAtClose,
                    'report' => $bySize
                ];
            } else {
                // Return empty day but with its pending balance
                $finalReports[] = [
                    'date' => $dateStr,
                    'total_damaged' => 0,
                    'total_pending' => (int)$pendingAtClose,
                    'report' => []
                ];
            }

            $currentDate->subDay();
        }

        return response()->json($finalReports);
    }

    /**
     * Remove the specified sorted production.
     */
    public function destroy(Production $production)
    {
        // Si tenía tamaño (buenos), revertir el stock de inventario
        if ($production->product_size_id && $production->useful_quantity > 0) {
            $inventory = \App\Models\Inventory::where('product_size_id', $production->product_size_id)->first();
            if ($inventory) {
                $inventory->decrement('units_available', $production->useful_quantity);
            }
        }

        $production->delete();

        return response()->json(['message' => 'Registro de producción eliminado y stock revertido']);
    }
    /*
     * Get pending eggs from previous days (Total Collected - Total Sorted up until date - 1).
     */
    public function pendingBalance(Request $request)
    {
        $date = $request->date ? Carbon::parse($request->date) : Carbon::now();
        $dateStr = $date->toDateString();

        $totalCollected = \App\Models\BatchCollection::whereDate('date', '<', $dateStr)->sum('quantity');
        $totalSorted = Production::whereDate('date', '<', $dateStr)->sum(DB::raw('useful_quantity + damaged_quantity'));

        $pending = max(0, $totalCollected - $totalSorted);

        return response()->json([
            'date' => $dateStr,
            'pending_from_yesterday' => (int)$pending
        ]);
    }
}
