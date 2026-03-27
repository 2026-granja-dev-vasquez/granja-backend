<?php

namespace App\Http\Controllers;

use App\Models\ProductSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductSizeController extends Controller
{
    public function index()
    {
        return response()->json(ProductSize::where('is_active', true)->get());
    }

    public function store(Request $request)
    {
        \Log::info('Store ProductSize Request:', $request->all());
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'unit_price'   => 'required|numeric|min:0',
            'carton_price' => 'required|numeric|min:0',
            'box_price'    => 'required|numeric|min:0',
        ]);

        $productSize = ProductSize::create($validated);

        return response()->json($productSize, 201);
    }

    public function update(Request $request, ProductSize $productSize)
    {
        \Log::info('Update ProductSize Request ID ' . $productSize->id . ':', $request->all());
        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'unit_price'   => 'sometimes|numeric|min:0',
            'carton_price' => 'sometimes|numeric|min:0',
            'box_price'    => 'sometimes|numeric|min:0',
            'is_active'    => 'sometimes|boolean',
        ]);

        $productSize->update($validated);

        return response()->json($productSize);
    }

    public function destroy(ProductSize $productSize)
    {
        $productSize->update(['is_active' => false]);
        return response()->json(['message' => 'Tamaño de producto desactivado.']);
    }
}
