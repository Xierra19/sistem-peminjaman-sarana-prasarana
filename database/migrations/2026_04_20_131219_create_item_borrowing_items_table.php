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
        Schema::create('item_borrowing_items', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('item_borrowing_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onUpdate('cascade');
            $table->integer('quantity');
            $table->date('borrow_date');
            $table->date('return_date');
            
            $table->timestamps();
            
            $table->unique(['item_borrowing_id', 'item_id']); // Prevent duplicate items in same request
            $table->index(['item_id', 'borrow_date', 'return_date']); // For overlap queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_borrowing_items');
    }
};
