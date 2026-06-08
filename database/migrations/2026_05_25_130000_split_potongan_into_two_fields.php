<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('inspections', 'potongan_kg')) {
            Schema::table('inspections', function (Blueprint $table) {
                $table->decimal('potongan_1_kg', 10, 2)->nullable()->after('lebar');
                $table->decimal('potongan_2_kg', 10, 2)->nullable()->after('potongan_1_kg');
            });

            DB::table('inspections')
                ->whereNotNull('potongan_kg')
                ->update(['potongan_1_kg' => DB::raw('potongan_kg')]);

            Schema::table('inspections', function (Blueprint $table) {
                $table->dropColumn('potongan_kg');
            });
        } elseif (! Schema::hasColumn('inspections', 'potongan_1_kg')) {
            Schema::table('inspections', function (Blueprint $table) {
                $table->decimal('potongan_1_kg', 10, 2)->nullable()->after('lebar');
                $table->decimal('potongan_2_kg', 10, 2)->nullable()->after('potongan_1_kg');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inspections', 'potongan_1_kg')) {
            Schema::table('inspections', function (Blueprint $table) {
                $table->decimal('potongan_kg', 10, 2)->nullable()->after('lebar');
            });

            DB::table('inspections')
                ->whereNotNull('potongan_1_kg')
                ->update(['potongan_kg' => DB::raw('potongan_1_kg')]);

            Schema::table('inspections', function (Blueprint $table) {
                $table->dropColumn(['potongan_1_kg', 'potongan_2_kg']);
            });
        }
    }
};
