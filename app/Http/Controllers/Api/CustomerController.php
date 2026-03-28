<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Listar todos los clientes.
     */
    public function index()
    {
        return response()->json(Customer::orderBy('name')->get());
    }

    /**
     * Crear un nuevo cliente.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $customer = Customer::create($request->all());

        return response()->json($customer, 201);
    }

    /**
     * Mostrar detalle del cliente e historial de compras (Próximamente).
     */
    public function show(Customer $customer)
    {
        // En el futuro incluiremos el historial de ventas
        // return response()->json($customer->load('sales'));
        return response()->json($customer);
    }

    /**
     * Actualizar cliente.
     */
    public function update(Request $request, Customer $customer)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $customer->update($request->all());

        return response()->json($customer);
    }

    /**
     * Eliminar (Desactivar) cliente.
     */
    public function destroy(Customer $customer)
    {
        // En lugar de borrar, desactivamos por integridad de reportes históricos
        $customer->update(['is_active' => false]);
        return response()->json(['message' => 'Cliente desactivado con éxito']);
    }
}
