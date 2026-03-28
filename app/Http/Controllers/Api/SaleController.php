<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * Listar todas las ventas con sus items y cliente.
     */
    public function index()
    {
        $sales = Sale::with(['customer', 'items.productSize'])
                    ->orderBy('date', 'desc')
                    ->get();
        return response()->json($sales);
    }

    /**
     * Crear una nueva venta y descontar inventario.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'   => 'nullable|exists:customers,id',
            'total_amount'  => 'required|numeric|min:0',
            'paid_amount'   => 'required|numeric|min:0',
            'status'        => 'required|in:pending,paid,partial',
            'date'          => 'required|date',
            'notes'         => 'nullable|string',
            'items'         => 'required|array|min:1',
            'items.*.product_size_id' => 'required|exists:product_sizes,id',
            'items.*.quantity'        => 'required|integer|min:1',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'items.*.subtotal'        => 'required|numeric|min:0',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                // 1. Crear la Venta
                $sale = Sale::create([
                    'customer_id'  => $validated['customer_id'],
                    'total_amount' => $validated['total_amount'],
                    'paid_amount'  => $validated['paid_amount'],
                    'status'       => $validated['status'],
                    'date'         => $validated['date'],
                    'notes'        => $validated['notes'],
                ]);

                // 2. Procesar cada Item e Inventory
                foreach ($validated['items'] as $itemData) {
                    $sale->items()->create([
                        'product_size_id' => $itemData['product_size_id'],
                        'quantity'        => $itemData['quantity'],
                        'unit_price'      => $itemData['unit_price'],
                        'subtotal'        => $itemData['subtotal'],
                    ]);

                    // DESCONTAR STOCK
                    $inventory = Inventory::where('product_size_id', $itemData['product_size_id'])->first();

                    if (!$inventory || $inventory->units_available < $itemData['quantity']) {
                        throw new \Exception("Stock insuficiente para el producto ID: " . $itemData['product_size_id']);
                    }

                    $inventory->decrement('units_available', $itemData['quantity']);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Venta registrada e inventario actualizado.',
                    'sale'    => $sale->load('items')
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Mostrar una venta específica.
     */
    public function show($id)
    {
        $sale = Sale::with(['customer', 'items.productSize'])->findOrFail($id);
        return response()->json($sale);
    }
}
