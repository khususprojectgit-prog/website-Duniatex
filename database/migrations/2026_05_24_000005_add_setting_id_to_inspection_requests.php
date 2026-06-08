<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->foreignId('setting_id')
                ->nullable()
                ->after('yarn_type_id')
                ->constrained('settings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->dropForeign(['setting_id']);
            $table->dropColumn('setting_id');
        });
    }
};
