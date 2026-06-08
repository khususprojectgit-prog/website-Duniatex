<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defects', function (Blueprint $table) {
            $table->enum('side', ['depan', 'belakang'])->default('depan')->after('point');
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->integer('manual_roll_number')->nullable()->after('roll_id');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn('manual_roll_number');
        });

        Schema::table('defects', function (Blueprint $table) {
            $table->dropColumn('side');
        });
    }
};
