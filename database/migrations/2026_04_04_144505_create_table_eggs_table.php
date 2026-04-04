<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_eggs', function (Blueprint $table) {
            $table->id();
            // date = the day when these eggs are REPORTED (today, when opening classification)
            $table->date('date');
            // product_size_id = size of the leftover eggs
            $table->foreignId('product_size_id')->constrained('product_sizes')->onDelete('cascade');
            // quantity = units left on the table from yesterday
            $table->integer('quantity')->default(0);
            $table->timestamps();

            // Only one entry per day per size
            $table->unique(['date', 'product_size_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_eggs');
    }
};
