<?php

// file: database/migrations/2025_11_03_000111_create_course_offerings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_offerings')) {
            Schema::create('course_offerings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
                $table->string('course_code', 50);
                $table->string('course_name', 150);
                $table->timestamps();

                $table->unique(['semester_id', 'course_code']);
            });

            return;
        }

        Schema::disableForeignKeyConstraints();

        if (Schema::hasColumn('course_offerings', 'class_group')) {
            $this->createIndexIfMissing('course_offerings', 'course_offerings_semester_id_index', ['semester_id']);
            $this->dropIndexIfExists('course_offerings', 'course_offerings_semester_id_course_code_class_group_unique');

            Schema::table('course_offerings', function (Blueprint $table) {
                if (Schema::hasColumn('course_offerings', 'class_group')) {
                    $table->dropColumn('class_group');
                }
            });
        }

        Schema::table('course_offerings', function (Blueprint $table) {
            if (!Schema::hasColumn('course_offerings', 'course_code')) {
                $table->string('course_code', 50);
            }

            if (!Schema::hasColumn('course_offerings', 'course_name')) {
                $table->string('course_name', 150);
            }
        });

        $this->createUniqueIfMissing('course_offerings', 'course_offerings_semester_id_course_code_unique', ['semester_id', 'course_code']);
        $this->dropIndexIfExists('course_offerings', 'course_offerings_semester_id_index');
        $this->dropIndexIfExists('course_offerings', 'course_offerings_semester_id_index');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (!Schema::hasTable('course_offerings')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        $this->dropIndexIfExists('course_offerings', 'course_offerings_semester_id_course_code_unique');

        Schema::table('course_offerings', function (Blueprint $table) {
            if (!Schema::hasColumn('course_offerings', 'class_group')) {
                $table->string('class_group', 20)->nullable()->after('course_name');
            }
        });

        $this->createIndexIfMissing('course_offerings', 'course_offerings_semester_id_index', ['semester_id']);
        $this->createUniqueIfMissing('course_offerings', 'course_offerings_semester_id_course_code_class_group_unique', ['semester_id', 'course_code', 'class_group']);
        $this->dropIndexIfExists('course_offerings', 'course_offerings_semester_id_index');

        Schema::enableForeignKeyConstraints();
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $exists = $connection->selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$database, $table, $index]
        );

        if (($exists->total ?? 0) > 0) {
            DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $index));
        }
    }

    private function createUniqueIfMissing(string $table, string $index, array $columns): void
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $exists = $connection->selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$database, $table, $index]
        );

        if (($exists->total ?? 0) === 0) {
            $cols = implode('`,`', $columns);
            DB::statement(sprintf('ALTER TABLE `%s` ADD UNIQUE `%s` (`%s`)', $table, $index, $cols));
        }
    }

    private function createIndexIfMissing(string $table, string $index, array $columns): void
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $exists = $connection->selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$database, $table, $index]
        );

        if (($exists->total ?? 0) === 0) {
            $cols = implode('`,`', $columns);
            DB::statement(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`%s`)', $table, $index, $cols));
        }
    }
};
