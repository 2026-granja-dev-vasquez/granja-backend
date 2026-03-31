<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BatchCollection;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BatchCollectionController extends Controller
{
    /**
     * Get collections per batch (Raw posture).
     */
    public function index(Request $request)
    {
        $query = BatchCollection::with('batch')->orderBy('date', 'desc');

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        } elseif ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->has('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        return response()->json($query->get());
    }

    /**
     * Store a raw collection for a batch.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'nullable|required_if:type,collection|exists:batches,id',
            'quantity' => 'required|integer',
            'date' => 'required|date',
            'type' => 'nullable|string|in:collection,adjustment',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Lógica para ajustes: solo un ajuste por día
        if ($request->type === 'adjustment') {
            $date = Carbon::parse($request->date)->toDateString();
            
            // Buscar por fecha ignorando la hora
            $collection = BatchCollection::whereDate('date', $date)
                ->where('type', 'adjustment')
                ->first();

            if ($collection) {
                $collection->update(['quantity' => $request->quantity]);
            } else {
                $collection = BatchCollection::create([
                    'date' => $date,
                    'type' => 'adjustment',
                    'quantity' => $request[ 'quantity' ],
                    'batch_id' => null
                ]);
            }
        } else {
            $collection = BatchCollection::create($request->all());
        }

        return response()->json([
            'message' => 'Recolecta de lote registrada',
            'data' => $collection->load('batch')
        ], 201);
    }
    
    /**
     * Get total raw eggs collected today.
     */
    public function dailyTotal(Request $request)
    {
        $date = $request->date ?? now()->toDateString();
        $total = BatchCollection::whereDate('date', $date)->sum('quantity');
        
        return response()->json(['date' => $date, 'total_raw_eggs' => (int)$total]);
    }

    /**
     * Get daily batch collection summaries for a given range.
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

        $query = BatchCollection::with('batch')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc');

        $collections = $query->get();

        // Group by date
        $grouped = $collections->groupBy(function($item) {
             return $item->date->toDateString();
        });

        $finalReports = [];
        $currentDate = $endDate->copy();

        while ($currentDate->greaterThanOrEqualTo($startDate)) {
            $dateStr = $currentDate->toDateString();
            
            if ($grouped->has($dateStr)) {
                $items = $grouped->get($dateStr);
                
                $byBatch = $items->groupBy('batch_id')->map(function($batchItems) {
                    $first = $batchItems->first();
                    $units = (int)$batchItems->sum('quantity');
                    $cartons = (int)floor($units / 30);
                    $leftover = $units % 30;

                    return [
                        'batch_id' => $first->batch_id,
                        'batch_name' => $first->batch->name,
                        'total_units' => $units,
                        'cartons' => $cartons,
                        'leftover_units' => $leftover,
                        'formatted' => "{$cartons} cartones y {$leftover} huevos"
                    ];
                })->values();

                $finalReports[] = [
                    'date' => $dateStr,
                    'report' => $byBatch
                ];
            } else {
                // Return empty day
                $finalReports[] = [
                    'date' => $dateStr,
                    'report' => []
                ];
            }

            $currentDate->subDay();
        }

        return response()->json($finalReports);
    }

    /**
     * Remove the specified raw collection record.
     */
    public function destroy(BatchCollection $daily_collection)
    {
        $daily_collection->delete();

        return response()->json([
            'message' => 'Recolecta de lote eliminada correctamente'
        ]);
    }
}
