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
        Schema::table('batch_collections', function (Blueprint $table) {
            // Permitir batch_id nulo para ajustes globales de inventario
            $table->unsignedBigInteger('batch_id')->nullable()->change();
            
            // Tipo de registro: 'collection' (por lote) o 'adjustment' (ajuste manual de saldo)
            $table->string('type')->default('collection')->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batch_collections', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable(false)->change();
            $table->dropColumn('type');
        });
    }
};
