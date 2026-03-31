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
            $table->dateTime('date')->change();
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->dateTime('date')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batch_collections', function (Blueprint $table) {
            $table->date('date')->change();
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->date('date')->change();
        });
    }
};
