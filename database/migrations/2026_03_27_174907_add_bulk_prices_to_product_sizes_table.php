<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->decimal('unit_price', 8, 2)->after('name')->nullable();
            $table->decimal('carton_price', 8, 2)->after('unit_price')->nullable();
            $table->decimal('box_price', 10, 2)->after('carton_price')->nullable();
            // Eliminamos la columna price anterior si queremos limpiar, pero por ahora la dejamos
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'carton_price', 'box_price']);
        });
    }
};
