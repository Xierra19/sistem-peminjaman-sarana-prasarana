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
        Schema::table('bookings', function (Blueprint $table): void {
$table->unsignedInteger('letter_sequence')->nullable()->after('status');
            $table->string('letter_number')->nullable()->after('letter_sequence');
            $table->timestamp('letter_generated_at')->nullable()->after('letter_number');
            $table->index('letter_generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['letter_generated_at']);
            $table->dropColumn([
                'letter_sequence',
                'letter_number',
                'letter_generated_at',
            ]);
        });
    }
};
