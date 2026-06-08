<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('defect_types', 'category')) {
            Schema::table('defect_types', function (Blueprint $table) {
                $table->string('category', 50)->nullable()->after('defect_name');
            });
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('inspection_requests', function (Blueprint $table) {
                $table->dropForeign(['qc_id']);
            });

            DB::statement('ALTER TABLE inspection_requests MODIFY qc_id BIGINT UNSIGNED NULL');

            Schema::table('inspection_requests', function (Blueprint $table) {
                $table->foreign('qc_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('defect_types', 'category')) {
            Schema::table('defect_types', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('inspection_requests', function (Blueprint $table) {
                $table->dropForeign(['qc_id']);
            });

            DB::statement('ALTER TABLE inspection_requests MODIFY qc_id BIGINT UNSIGNED NOT NULL');

            Schema::table('inspection_requests', function (Blueprint $table) {
                $table->foreign('qc_id')->references('id')->on('users')->restrictOnDelete();
            });
        }
    }
};
