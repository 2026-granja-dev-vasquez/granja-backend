<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashBox;
use App\Models\CashTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashBoxController extends Controller
{
    /**
     * Get historical cash box sessions.
     */
    public function index(Request $request)
    {
        $history = CashBox::orderBy('id', 'desc')
            ->paginate(15);

        return response()->json($history);
    }

    /**
     * Get details of a specific historical cash box session.
     */
    public function show($id)
    {
        $cashBox = CashBox::with(['transactions' => function($q) {
                $q->where('status', 'active');
            }])
            ->findOrFail($id);

        return response()->json($cashBox);
    }

    /**
     * Get the currently active cash box session.
     */
    public function current(Request $request)
    {
        $current = CashBox::where('status', 'open')
            ->with(['transactions' => function($q) {
                $q->where('status', 'active');
            }])
            ->latest()
            ->first();

        return response()->json($current);
    }

    /**
     * Open a new cash box session.
     */
    public function open(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        // Check if there is already an open box (Global check)
        $exists = CashBox::where('status', 'open')->exists();
            
        if ($exists) {
            return response()->json(['message' => 'Ya existe una caja abierta para la granja.'], 422);
        }

        $cashBox = CashBox::create([
            'name' => $request->name,
            'user_id' => $request->user()->id, // Track who opened it
            'opening_balance' => (float)$request->opening_balance,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        return response()->json($cashBox);
    }

    /**
     * Register a manual transaction (income/expense).
     */
    public function storeTransaction(Request $request)
    {
        $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'required|string',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
        ]);

        $cashBox = CashBox::where('status', 'open')->first();
            
        if (!$cashBox) {
            return response()->json(['message' => 'No hay una caja abierta para registrar movimientos.'], 422);
        }

        return DB::transaction(function () use ($request, $cashBox) {
            $transaction = $cashBox->transactions()->create([
                'type' => $request->type,
                'amount' => (float)$request->amount,
                'category' => $request->category,
                'description' => $request->description,
                'created_at' => $request->date ? $request->date : now(),
            ]);

            // Update totals in the box
            if ($request->type === 'income') {
                $cashBox->increment('total_income', (float)$request->amount);
            } else {
                $cashBox->increment('total_expense', (float)$request->amount);
            }

            return response()->json($transaction);
        });
    }

    /**
     * Close the active cash box session.
     */
    public function close(Request $request)
    {
        $cashBox = CashBox::where('status', 'open')->first();
            
        if (!$cashBox) {
            return response()->json(['message' => 'No hay ninguna caja abierta.'], 422);
        }

        // Calculate theoretical closing balance
        $theoreticalBalance = (float)$cashBox->opening_balance + (float)$cashBox->total_income - (float)$cashBox->total_expense;

        $cashBox->update([
            'closing_balance' => $theoreticalBalance,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json($cashBox);
    }


    /**
     * Update a cash box session (e.g., change name).
     */
    public function update(Request $request, CashBox $cashBox)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $cashBox->update([
            'name' => $request->name,
        ]);

        return response()->json($cashBox);
    }
}
