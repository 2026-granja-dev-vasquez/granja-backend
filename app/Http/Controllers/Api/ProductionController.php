<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Production;
use Illuminate\Support\Facades\Validator;
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

        return response()->json([
            'message' => 'Clasificación registrada con éxito',
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
            $endDate = Carbon::now();
            $startDate = Carbon::now()->subDays(2);
        } else {
            $startDate = $startDateStr ? Carbon::parse($startDateStr) : Carbon::parse($endDateStr);
            $endDate = Carbon::parse($endDateStr);
        }

        $query = Production::with('productSize')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
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
                    'report' => $bySize
                ];
            } else {
                // Return empty day
                $finalReports[] = [
                    'date' => $dateStr,
                    'total_damaged' => 0,
                    'report' => []
                ];
            }

            $currentDate->subDay();
        }

        return response()->json($finalReports);
    }
}
