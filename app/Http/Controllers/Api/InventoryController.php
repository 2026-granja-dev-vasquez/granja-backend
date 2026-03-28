<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Get current stock levels for all product sizes.
     */
    public function index()
    {
        $status = Inventory::with('productSize')->get()->map(function($item) {
            $units = $item->units_available;
            $cartons = (int)floor($units / 30);
            $leftover = $units % 30;

            return [
                'product_size_id' => $item->product_size_id,
                'name' => $item->productSize->name,
                'total_units' => $units,
                'cartons' => $cartons,
                'leftover_units' => $leftover,
                'formatted' => "{$cartons} cartones y {$leftover} huevos"
            ];
        });

        return response()->json($status);
    }
}
