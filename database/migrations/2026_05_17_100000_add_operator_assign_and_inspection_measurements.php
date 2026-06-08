<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->foreignId('operator_id')
                ->nullable()
                ->after('machine_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('length_meter', 10, 2)->nullable()->change();
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->enum('shift', ['pagi', 'siang', 'malam'])->nullable()->after('operator_id');
            $table->decimal('length_meter', 10, 2)->nullable()->after('shift');
            $table->decimal('weight_kg', 10, 2)->nullable()->after('length_meter');
            $table->decimal('gramasi', 8, 2)->nullable()->after('weight_kg');
            $table->string('yarn_name', 100)->nullable()->after('gramasi');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn(['shift', 'length_meter', 'weight_kg', 'gramasi', 'yarn_name']);
        });

        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->dropForeign(['operator_id']);
            $table->dropColumn('operator_id');
            $table->decimal('length_meter', 10, 2)->nullable(false)->change();
        });
    }
};
