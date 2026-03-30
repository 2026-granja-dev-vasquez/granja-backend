<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Sale;
use App\Models\Inventory;
use App\Models\CashBox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['customer', 'items.productSize'])
            ->whereIn('status', ['pending', 'postponed'])
            ->orderBy('delivery_date', 'asc')
            ->get();
            
        return response()->json($orders);
    }

    public function history(Request $request)
    {
        $query = Order::with(['customer', 'items.productSize'])
            ->whereIn('status', ['delivered', 'cancelled'])
            ->orderBy('delivery_date', 'desc');

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('delivery_date', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'delivery_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_size_id' => 'required|exists:product_sizes,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        return \DB::transaction(function() use ($request) {
            $order = Order::create([
                'customer_id' => $request->customer_id,
                'delivery_date' => $request->delivery_date,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $order->items()->create([
                    'product_size_id' => $item['product_size_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return response()->json($order->load(['customer', 'items.productSize']), 201);
        });
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status'        => 'required|in:pending,delivered,postponed,cancelled',
            'delivery_date' => 'nullable|date',
            'notes'         => 'nullable|string',
            'create_sale'   => 'nullable|boolean',
            'sale_data'     => 'nullable|array',
            'sale_data.paid_amount' => 'nullable|numeric',
            'sale_data.items'       => 'nullable|array',
        ]);

        return DB::transaction(function() use ($request, $order) {
            $order->status = $request->status;
            
            if ($request->has('delivery_date')) {
                $order->delivery_date = $request->delivery_date;
            }
            
            if ($request->has('notes')) {
                $order->notes = $request->notes;
            }

            $order->save();

            // 1. SI SE MARCA COMO ENTREGADO, SIEMPRE DESCONTAR STOCK
            if ($request->status === 'delivered') {
                foreach ($order->items as $orderItem) {
                    $inventory = Inventory::where('product_size_id', $orderItem->product_size_id)->first();
                    if ($inventory) {
                        $inventory->decrement('units_available', $orderItem->quantity);
                    }
                }
            }

            // 2. SI SE SOLICITA CREAR VENTA (Solo al entregar)
            if ($request->status === 'delivered' && $request->create_sale && $request->has('sale_data')) {
                $saleData = $request->sale_data;
                $totalAmount = 0;
                
                // VALIDAR CAJA SI HAY PAGO
                $paidAmount = (float)($saleData['paid_amount'] ?? 0);
                if ($paidAmount > 0) {
                    $hasActiveBox = CashBox::where('status', 'open')->exists();
                    if (!$hasActiveBox) {
                        throw new \Exception("No hay una caja abierta para registrar el pago.");
                    }
                }

                // Calcular total desde los items enviados (precios dinámicos)
                foreach ($saleData['items'] as $item) {
                    $totalAmount += (float)$item['quantity'] * (float)$item['unit_price'];
                }

                // Crear la Venta
                $sale = Sale::create([
                    'customer_id'  => $order->customer_id,
                    'total_amount' => $totalAmount,
                    'paid_amount'  => $paidAmount,
                    'status'       => $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending'),
                    'date'         => $saleData['date'] ?? now(),
                    'notes'        => $saleData['notes'] ?? ("Venta generada desde Pedido #" . $order->id),
                ]);

                // Procesar Items y Subtotales (Nota: Stock ya se descontó arriba)
                foreach ($saleData['items'] as $itemData) {
                    $subtotal = (float)$itemData['quantity'] * (float)$itemData['unit_price'];
                    
                    $sale->items()->create([
                        'product_size_id' => $itemData['product_size_id'],
                        'quantity'        => $itemData['quantity'],
                        'unit_price'      => $itemData['unit_price'],
                        'subtotal'        => $subtotal,
                    ]);
                }

                // REGISTRAR EN CAJA
                if ($paidAmount > 0) {
                    $activeCashBox = CashBox::where('status', 'open')->first();
                    $activeCashBox->transactions()->create([
                        'type'           => 'income',
                        'amount'         => $paidAmount,
                        'category'       => 'Venta (Pedido)',
                        'description'    => "Venta desde Pedido #{$order->id} - " . ($order->customer ? $order->customer->name : 'Varios'),
                        'reference_id'   => $sale->id,
                        'reference_type' => Sale::class,
                    ]);
                    $activeCashBox->increment('total_income', $paidAmount);
                }
            }

            return response()->json($order->load(['customer', 'items.productSize']));
        });
    }
}
