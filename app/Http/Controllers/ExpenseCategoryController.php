<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    /** List all active (non-deleted) expense categories. */
    public function index()
    {
        $categories = ExpenseCategory::orderBy('name', 'asc')->get();
        return response()->json($categories);
    }

    /** Create a new expense category. */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $category = ExpenseCategory::create([
            'name' => $request->name,
        ]);

        return response()->json($category, 201);
    }

    /** Rename an existing expense category. */
    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $expenseCategory->update(['name' => $request->name]);

        return response()->json($expenseCategory);
    }

    /** Soft-delete an expense category. */
    public function destroy(ExpenseCategory $expenseCategory)
    {
        $expenseCategory->delete();
        return response()->json(['message' => 'Rubro eliminado correctamente.']);
    }
}
