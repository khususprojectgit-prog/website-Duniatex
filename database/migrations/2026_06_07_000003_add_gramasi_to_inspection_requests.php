<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->string('gramasi', 50)->nullable()->after('setting_id');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->dropColumn('gramasi');
        });
    }
};
