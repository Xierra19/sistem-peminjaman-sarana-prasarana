<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_borrowings', function (Blueprint $table): void {
            if (! Schema::hasColumn('item_borrowings', 'title')) {
                $table->string('title')->after('user_id');
            }

            if (! Schema::hasColumn('item_borrowings', 'description')) {
                $table->text('description')->nullable()->after('quantity');
            }

            if (! Schema::hasColumn('item_borrowings', 'attachment')) {
                $table->string('attachment')->nullable()->after('status');
            }

            if (! Schema::hasColumn('item_borrowings', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('attachment');
            }

            if (! Schema::hasColumn('item_borrowings', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('item_borrowings', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('item_borrowings', 'returned_by')) {
                $table->foreignId('returned_by')->nullable()->after('returned_at')->constrained('users')->nullOnDelete();
            }

            if (Schema::hasColumn('item_borrowings', 'notes')) {
                $table->dropColumn('notes');
            }

            $table->index(['item_id', 'borrow_date', 'return_date'], 'item_borrowings_item_period_index');
            $table->index(['status', 'borrow_date'], 'item_borrowings_status_borrow_date_index');
        });

        DB::table('item_borrowings')
            ->where('status', 'requested')
            ->update(['status' => 'waiting']);

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `item_borrowings` MODIFY `status` VARCHAR(255) NOT NULL DEFAULT 'waiting'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `item_borrowings` MODIFY `status` VARCHAR(255) NOT NULL DEFAULT 'requested'");
        }

        Schema::table('item_borrowings', function (Blueprint $table): void {
            $table->dropIndex('item_borrowings_item_period_index');
            $table->dropIndex('item_borrowings_status_borrow_date_index');

            if (Schema::hasColumn('item_borrowings', 'returned_by')) {
                $table->dropConstrainedForeignId('returned_by');
            }

            if (Schema::hasColumn('item_borrowings', 'returned_at')) {
                $table->dropColumn('returned_at');
            }

            if (Schema::hasColumn('item_borrowings', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('item_borrowings', 'approved_at')) {
                $table->dropColumn('approved_at');
            }

            if (Schema::hasColumn('item_borrowings', 'attachment')) {
                $table->dropColumn('attachment');
            }

            if (Schema::hasColumn('item_borrowings', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('item_borrowings', 'title')) {
                $table->dropColumn('title');
            }

            if (! Schema::hasColumn('item_borrowings', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });
    }
};
