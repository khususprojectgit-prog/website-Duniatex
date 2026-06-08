<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yarn_types', function (Blueprint $table) {
            $table->dropColumn('count');
        });
    }

    public function down(): void
    {
        Schema::table('yarn_types', function (Blueprint $table) {
            $table->string('count', 50)->nullable()->after('material');
        });
    }
};
