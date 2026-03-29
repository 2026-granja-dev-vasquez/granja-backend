<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('customer')
            ->whereIn('status', ['pending', 'postponed'])
            ->orderBy('delivery_date', 'asc')
            ->get();
            
        return response()->json($orders);
    }

    public function history(Request $request)
    {
        $query = Order::with('customer')
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
        ]);

        $order = Order::create([
            'customer_id' => $request->customer_id,
            'delivery_date' => $request->delivery_date,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        return response()->json($order->load('customer'), 201);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,delivered,postponed,cancelled',
            'delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $order->status = $request->status;
        
        if ($request->has('delivery_date')) {
            $order->delivery_date = $request->delivery_date;
        }
        
        if ($request->has('notes')) {
            $order->notes = $request->notes;
        }

        $order->save();

        return response()->json($order->load('customer'));
    }
}
