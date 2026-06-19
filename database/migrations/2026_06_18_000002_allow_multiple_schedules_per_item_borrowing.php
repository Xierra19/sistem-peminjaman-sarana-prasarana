<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may use the old composite unique index to support this foreign key.
        // Give the foreign key its own index before replacing the unique constraint.
        Schema::table('item_borrowing_items', function (Blueprint $table): void {
            $table->index(
                'item_borrowing_id',
                'item_borrowing_items_borrowing_fk_index',
            );
        });

        Schema::table('item_borrowing_items', function (Blueprint $table): void {
            $table->dropUnique(['item_borrowing_id', 'item_id']);
        });

        Schema::table('item_borrowing_items', function (Blueprint $table): void {
            $table->unique(
                ['item_borrowing_id', 'item_id', 'quantity', 'borrow_date', 'return_date'],
                'item_borrowing_items_schedule_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('item_borrowing_items', function (Blueprint $table): void {
            $table->dropUnique('item_borrowing_items_schedule_unique');
        });

        Schema::table('item_borrowing_items', function (Blueprint $table): void {
            $table->unique(['item_borrowing_id', 'item_id']);
        });

        Schema::table('item_borrowing_items', function (Blueprint $table): void {
            $table->dropIndex('item_borrowing_items_borrowing_fk_index');
        });
    }
};
