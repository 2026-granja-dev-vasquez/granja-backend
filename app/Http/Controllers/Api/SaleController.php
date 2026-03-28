<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Inventory;
use App\Models\CashBox;
use App\Models\CashTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * Listar ventas con filtros (fecha, cliente).
     */
    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'items.productSize']);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        $sales = $query->orderBy('date', 'desc')->get();
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
                // 0. VALIDAR CAJA ABIERTA (Si es venta pagada o parcial)
                if ($validated['status'] !== 'pending' || $validated['paid_amount'] > 0) {
                    $hasActiveBox = CashBox::where('status', 'open')->exists();
                    if (!$hasActiveBox) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No hay una caja abierta. Por favor, abre una sesión primero para registrar pagos.',
                            'code' => 'CASH_BOX_CLOSED'
                        ], 422);
                    }
                }

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

                // 3. REGISTRAR EN CAJA (Si la venta es pagada y hay una caja abierta)
                if ($sale->status === 'paid' || $sale->paid_amount > 0) {
                    $activeCashBox = CashBox::where('status', 'open')->first();
                    if ($activeCashBox) {
                        $activeCashBox->transactions()->create([
                            'type'           => 'income',
                            'amount'         => (float)$sale->paid_amount,
                            'category'       => 'Venta',
                            'description'    => "Venta #{$sale->id} - " . ($sale->customer ? $sale->customer->name : 'Consumidor Final'),
                            'reference_id'   => $sale->id,
                            'reference_type' => Sale::class,
                        ]);
                        $activeCashBox->increment('total_income', (float)$sale->paid_amount);
                    }
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

    /**
     * Actualizar estado de pago de una venta.
     */
    public function update(Request $request, $id)
    {
        $sale = Sale::findOrFail($id);

        $validated = $request->validate([
            'paid_amount' => 'nullable|numeric|min:0',
            'status'      => 'nullable|in:pending,paid,partial',
            'notes'       => 'nullable|string',
        ]);

        $oldStatus = $sale->status;
        $sale->update($validated);

        // REGISTRAR EN CAJA (Si cambia a pagada o ya era pagada pero se actualizó el monto)
        if (($oldStatus !== 'paid' && $sale->status === 'paid') || ($sale->status === 'paid' && isset($validated['paid_amount']))) {
            $activeCashBox = CashBox::where('status', 'open')->first();
            if ($activeCashBox) {
                $amountToRecord = $validated['paid_amount'] ?? $sale->paid_amount;
                
                $activeCashBox->transactions()->create([
                    'type'           => 'income',
                    'amount'         => $amountToRecord,
                    'category'       => 'Venta',
                    'description'    => "COBRO de " . ($sale->customer ? $sale->customer->name : 'Consumidor Final') . " por fiado (Venta #{$sale->id})",
                    'reference_id'   => $sale->id,
                    'reference_type' => Sale::class,
                ]);
                $activeCashBox->increment('total_income', (float)$amountToRecord);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Venta actualizada correctamente.',
            'sale'    => $sale->load(['customer', 'items.productSize'])
        ]);
    }
}
