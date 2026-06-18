<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('role')->index();
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->text('deactivation_reason')->nullable()->after('deactivated_at');
            $table->foreignId('deactivated_by')
                ->nullable()
                ->after('deactivation_reason')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['deactivated_by']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'is_active',
                'deactivated_at',
                'deactivation_reason',
                'deactivated_by',
            ]);
        });
    }
};
