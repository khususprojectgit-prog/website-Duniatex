<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('result', 10)->nullable()->change();
        });

        DB::table('inspections')->where('result', 'PASS')->update(['result' => 'A']);
        DB::table('inspections')->where('result', 'FAIL')->update(['result' => 'BS']);

        Schema::table('inspections', function (Blueprint $table) {
            $table->string('result', 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('result', 10)->nullable()->change();
        });

        DB::table('inspections')->where('result', 'A')->update(['result' => 'PASS']);
        DB::table('inspections')->whereIn('result', ['B', 'BS'])->update(['result' => 'FAIL']);

        Schema::table('inspections', function (Blueprint $table) {
            $table->enum('result', ['PASS', 'FAIL'])->nullable()->change();
        });
    }
};
