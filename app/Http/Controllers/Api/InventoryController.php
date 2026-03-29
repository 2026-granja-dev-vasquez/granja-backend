<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\ProductSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Get current stock levels for all product sizes.
     */
    public function index()
    {
        // Get all product sizes and join with inventories to get available units
        $status = ProductSize::orderBy('id', 'asc')->get()->map(function($size) {
            $inventory = Inventory::where('product_size_id', $size->id)->first();
            $units = $inventory ? $inventory->units_available : 0;
            
            $cartons = (int)floor($units / 30);
            $leftover = $units % 30;

            return [
                'product_size_id' => $size->id,
                'name' => $size->name,
                'total_units' => $units,
                'cartons' => $cartons,
                'leftover_units' => $leftover,
                'formatted' => "{$cartons} cartones y {$leftover} huevos"
            ];
        });

        return response()->json($status);
    }

    /**
     * Adjust stock manually (Add or Remove)
     */
    public function adjust(Request $request)
    {
        $request->validate([
            'product_size_id' => 'required|exists:product_sizes,id',
            'type' => 'required|in:in,out',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        return DB::transaction(function() use ($request) {
            // 1. Log Adjustment
            $adjustment = InventoryAdjustment::create([
                'product_size_id' => $request->product_size_id,
                'type' => $request->type,
                'quantity' => $request->quantity,
                'reason' => $request->reason,
                'user_id' => $request->user()->id,
            ]);

            // 2. Update Inventory
            $inventory = Inventory::firstOrCreate(
                ['product_size_id' => $request->product_size_id],
                ['units_available' => 0]
            );

            if ($request->type === 'in') {
                $inventory->increment('units_available', $request->quantity);
            } else {
                // Ensure we don't go below zero if it's an outing (unless requested otherwise)
                // For now, allow it but maybe a warning later
                $inventory->decrement('units_available', $request->quantity);
            }

            return response()->json([
                'message' => 'Inventario ajustado correctamente',
                'new_total' => $inventory->units_available
            ]);
        });
    }
}
