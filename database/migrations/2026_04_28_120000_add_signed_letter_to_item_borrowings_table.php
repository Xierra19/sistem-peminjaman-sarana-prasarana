<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_borrowings', function (Blueprint $table): void {
            if (! Schema::hasColumn('item_borrowings', 'signed_letter')) {
                $table->string('signed_letter')->nullable()->after('attachment');
            }

            if (! Schema::hasColumn('item_borrowings', 'signed_letter_uploaded_at')) {
                $table->timestamp('signed_letter_uploaded_at')->nullable()->after('signed_letter');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_borrowings', function (Blueprint $table): void {
            if (Schema::hasColumn('item_borrowings', 'signed_letter_uploaded_at')) {
                $table->dropColumn('signed_letter_uploaded_at');
            }

            if (Schema::hasColumn('item_borrowings', 'signed_letter')) {
                $table->dropColumn('signed_letter');
            }
        });
    }
};
