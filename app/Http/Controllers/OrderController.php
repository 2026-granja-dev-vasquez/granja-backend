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
            'items.*.unit_price' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function() use ($request) {
            // 1. Calcular total del pedido
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += (float)$item['quantity'] * (float)$item['unit_price'];
            }

            // 2. Crear Pedido
            $order = Order::create([
                'customer_id'   => $request->customer_id,
                'delivery_date' => $request->delivery_date,
                'status'        => 'pending',
                'notes'         => $request->notes,
                'total_amount'  => $totalAmount,
                'paid_amount'   => (float)($request->paid_amount ?? 0),
            ]);

            // 3. Crear Items
            foreach ($request->items as $item) {
                $subtotal = (float)$item['quantity'] * (float)$item['unit_price'];
                $order->items()->create([
                    'product_size_id' => $item['product_size_id'],
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'subtotal'        => $subtotal,
                ]);
            }

            // 4. Si hay pago inicial, registrar en Caja
            $paidAmount = (float)($request->paid_amount ?? 0);
            if ($paidAmount > 0) {
                $activeCashBox = CashBox::where('status', 'open')->first();
                if (!$activeCashBox) {
                    throw new \Exception("No hay una caja abierta para registrar el pago.");
                }

                $activeCashBox->transactions()->create([
                    'type'           => 'income',
                    'amount'         => $paidAmount,
                    'category'       => 'Abono (Pedido)',
                    'description'    => "Abono inicial Pedido #{$order->id} - " . ($order->customer->name ?? 'Cliente'),
                    'reference_id'   => $order->id,
                    'reference_type' => Order::class,
                ]);
                $activeCashBox->increment('total_income', $paidAmount);
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
            $oldStatus = $order->status;
            $order->status = $request->status;
            
            if ($request->has('delivery_date')) {
                $order->delivery_date = $request->delivery_date;
            }
            
            if ($request->has('notes')) {
                $order->notes = $request->notes;
            }

            $order->save();

            // 1. SI SE MARCA COMO ENTREGADO (Y NO LO ESTABA), DESCONTAR STOCK
            if ($request->status === 'delivered' && $oldStatus !== 'delivered') {
                foreach ($order->items as $orderItem) {
                    $inventory = Inventory::where('product_size_id', $orderItem->product_size_id)->first();
                    if ($inventory) {
                        $inventory->decrement('units_available', $orderItem->quantity);
                    }
                }
            }

            // 2. SI SE CANCELA, ANULAR TRANSACCIONES DE CAJA ASOCIADAS
            if ($request->status === 'cancelled') {
                $this->voidOrderTransactions($order);
            }

            // 3. SI SE SOLICITA CREAR VENTA (Solo al entregar)
            if ($request->status === 'delivered' && $request->create_sale && $request->has('sale_data')) {
                $saleData = $request->sale_data;
                $totalAmount = 0;
                
                // VALIDAR CAJA SI HAY PAGO ADICIONAL (Diferencia)
                $additionalPaid = (float)($saleData['paid_amount'] ?? 0);
                if ($additionalPaid > 0) {
                    $hasActiveBox = CashBox::where('status', 'open')->exists();
                    if (!$hasActiveBox) {
                        throw new \Exception("No hay una caja abierta para registrar el pago.");
                    }
                }

                // Calcular total Real (por si cambió en el momento de entrega)
                foreach ($saleData['items'] as $item) {
                    $totalAmount += (float)$item['quantity'] * (float)$item['unit_price'];
                }

                // Crear la Venta
                $sale = Sale::create([
                    'customer_id'  => $order->customer_id,
                    'total_amount' => $totalAmount,
                    'paid_amount'  => $order->paid_amount + $additionalPaid,
                    'status'       => ($order->paid_amount + $additionalPaid) >= $totalAmount ? 'paid' : 'partial',
                    'date'         => $saleData['date'] ?? now(),
                    'notes'        => $saleData['notes'] ?? ("Entrega Pedido #{$order->id}"),
                ]);

                // Crear Items de Venta
                foreach ($saleData['items'] as $itemData) {
                    $sale->items()->create([
                        'product_size_id' => $itemData['product_size_id'],
                        'quantity'        => $itemData['quantity'],
                        'unit_price'      => $itemData['unit_price'],
                        'subtotal'        => (float)$itemData['quantity'] * (float)$itemData['unit_price'],
                    ]);
                }

                // REGISTRAR SOLO EL PAGO ADICIONAL EN CAJA
                if ($additionalPaid > 0) {
                    $activeCashBox = CashBox::where('status', 'open')->first();
                    $activeCashBox->transactions()->create([
                        'type'           => 'income',
                        'amount'         => $additionalPaid,
                        'category'       => 'Venta (Pedido)',
                        'description'    => "Pago final Pedido #{$order->id} - " . ($order->customer->name ?? 'Cliente'),
                        'reference_id'   => $sale->id,
                        'reference_type' => Sale::class,
                    ]);
                    $activeCashBox->increment('total_income', $additionalPaid);
                }

                // Actualizar el pagado del pedido original por integridad
                $order->increment('paid_amount', $additionalPaid);
            }

            return response()->json($order->load(['customer', 'items.productSize']));
        });
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'customer_id'              => 'required|exists:customers,id',
            'delivery_date'            => 'required|date',
            'items'                    => 'required|array|min:1',
            'items.*.product_size_id'  => 'required|exists:product_sizes,id',
            'items.*.quantity'         => 'required|integer|min:1',
            'items.*.unit_price'       => 'required|numeric|min:0',
            'notes'                    => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $order) {
            // Recalcular total
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += (float)$item['quantity'] * (float)$item['unit_price'];
            }

            // Actualizar datos del pedido
            $order->update([
                'customer_id'   => $request->customer_id,
                'delivery_date' => $request->delivery_date,
                'total_amount'  => $totalAmount,
                'notes'         => $request->notes,
            ]);

            // Reemplazar items del pedido
            $order->items()->delete();
            foreach ($request->items as $item) {
                $subtotal = (float)$item['quantity'] * (float)$item['unit_price'];
                $order->items()->create([
                    'product_size_id' => $item['product_size_id'],
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'subtotal'        => $subtotal,
                ]);
            }

            return response()->json($order->load(['customer', 'items.productSize']));
        });
    }

    public function destroy(Order $order)
    {
        return DB::transaction(function() use ($order) {
            // Anular transacciones financieras asociadas
            $this->voidOrderTransactions($order);
            
            // Eliminar pedido
            $order->delete();

            return response()->json(['message' => 'Pedido eliminado y transacciones anuladas.']);
        });
    }

    /**
     * Anula todas las transacciones vinculadas a un pedido (Abonos).
     */
    private function voidOrderTransactions(Order $order)
    {
        $transactions = $order->transactions()->where('status', 'active')->get();

        foreach ($transactions as $transaction) {
            $transaction->update(['status' => 'voided']);
            
            // Restar del total de la caja a la que pertenece
            $cashBox = $transaction->cashBox;
            if ($cashBox) {
                if ($transaction->type === 'income') {
                    $cashBox->decrement('total_income', $transaction->amount);
                } else {
                    $cashBox->decrement('total_expense', $transaction->amount);
                }
            }
        }
    }
}
