<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashBox;
use App\Models\CashTransaction;
use Illuminate\Support\Carbon;
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
        $cashBox = CashBox::with(['transactions.reference'])
            ->findOrFail($id);

        return response()->json($cashBox);
    }

    /**
     * Get the currently active cash box session.
     */
    public function current(Request $request)
    {
        $current = CashBox::where('status', 'open')
            ->with(['transactions.reference'])
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
            'date' => 'nullable|date',
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
            'opened_at' => $this->localDateTime($request->date),
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
                'created_at' => $this->localDateTime($request->date),
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
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $cashBox = CashBox::where('status', 'open')->first();
            
        if (!$cashBox) {
            return response()->json(['message' => 'No hay ninguna caja abierta.'], 422);
        }

        // Calculate theoretical closing balance
        $theoreticalBalance = (float)$cashBox->opening_balance + (float)$cashBox->total_income - (float)$cashBox->total_expense;

        $cashBox->update([
            'closing_balance' => $theoreticalBalance,
            'status' => 'closed',
            'closed_at' => $this->localDateTime($request->date),
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

    /**
     * Update the category (rubro) of a transaction.
     */
    public function updateTransaction(Request $request, $id)
    {
        $request->validate([
            'category' => 'required|string|max:100',
        ]);

        $transaction = \App\Models\CashTransaction::findOrFail($id);
        $transaction->update(['category' => $request->category]);

        return response()->json($transaction);
    }

    /**
     * Void a specific transaction.
     */
    public function voidTransaction(Request $request, $id)
    {
        // Safety check for Admin
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Solo los administradores pueden anular transacciones.'], 403);
        }

        $request->validate([
            'void_reason' => 'required|string|min:3',
        ]);

        $transaction = CashTransaction::findOrFail($id);

        if ($transaction->status === 'voided') {
            return response()->json(['message' => 'Esta transacción ya está anulada.'], 422);
        }

        $cashBox = $transaction->cashBox;

        if ($cashBox->status !== 'open') {
            return response()->json(['message' => 'Solo se pueden anular transacciones de una caja abierta.'], 422);
        }

        return DB::transaction(function () use ($transaction, $request, $cashBox) {
            $transaction->update([
                'status' => 'voided',
                'void_reason' => $request->void_reason,
            ]);

            // Adjust totals in the box
            if ($transaction->type === 'income') {
                $cashBox->decrement('total_income', $transaction->amount);
            } else {
                $cashBox->decrement('total_expense', $transaction->amount);
            }

            return response()->json(['message' => 'Transacción anulada con éxito.', 'transaction' => $transaction]);
        });
    }

    private function localDateTime(?string $dateTime = null): string
    {
        return Carbon::parse($dateTime ?? now())->format('Y-m-d H:i:s');
    }
}
