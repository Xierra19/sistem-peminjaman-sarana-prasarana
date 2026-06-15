<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'bookings_letter_number_unique';

    public function up(): void
    {
        DB::table('bookings')
            ->whereNotNull('letter_number')
            ->whereRaw("TRIM(letter_number) = ''")
            ->update(['letter_number' => null]);

        $duplicate = DB::table('bookings')
            ->select('letter_number')
            ->whereNotNull('letter_number')
            ->groupBy('letter_number')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('letter_number')
            ->value('letter_number');

        if ($duplicate !== null) {
            throw new \RuntimeException(
                "Cannot enforce unique booking letter numbers because duplicate value [{$duplicate}] exists.",
            );
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unique('letter_number', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
