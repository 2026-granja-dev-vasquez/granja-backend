<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TableEgg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TableEggController extends Controller
{
    /**
     * Get table eggs for a given date.
     */
    public function index(Request $request)
    {
        $date = $request->date ?? now()->toDateString();
        $eggs = TableEgg::with('productSize')
            ->where('date', $date)
            ->get();

        return response()->json($eggs);
    }

    /**
     * Store or update (upsert) table egg entry for a date + size.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'            => 'required|date',
            'product_size_id' => 'required|exists:product_sizes,id',
            'quantity'        => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $date = Carbon::parse($request->date)->toDateString();

        $egg = TableEgg::updateOrCreate(
            [
                'date'            => $date,
                'product_size_id' => $request->product_size_id,
            ],
            ['quantity' => $request->quantity]
        );

        return response()->json([
            'message' => 'Huevos en mesa registrados',
            'data'    => $egg->load('productSize'),
        ], 201);
    }

    /**
     * Remove all table egg entries for a specific date.
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate(['date' => 'required|date']);
        $date = Carbon::parse($request->date)->toDateString();
        
        TableEgg::where('date', $date)->delete();
        
        return response()->json(['message' => 'Huevos en mesa eliminados para la fecha solicitada']);
    }

    /**
     * Remove a specific table egg entry.
     */
    public function destroy(TableEgg $table_egg)
    {
        $table_egg->delete();
        return response()->json(['message' => 'Huevo en mesa eliminado']);
    }
}
