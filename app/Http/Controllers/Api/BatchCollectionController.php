<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BatchCollection;
use Illuminate\Support\Facades\Validator;

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
            $query->whereDate('date', '>=', $request->start_date);
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
            'batch_id' => 'required|exists:batches,id',
            'quantity' => 'required|integer|min:1',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $collection = BatchCollection::create($request->all());

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
}
