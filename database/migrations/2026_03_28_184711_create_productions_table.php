<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_size_id')->constrained()->onDelete('cascade');
            $table->integer('useful_quantity'); // Cantidad de huevos útiles por tamaño
            $table->integer('damaged_quantity')->default(0); // Huevos quebrados encontrados al clasificar
            $table->date('date'); // Fecha de la clasificación
            $table->timestamps();
            
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
