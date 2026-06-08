<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->foreignId('yarn_type_id')
                ->nullable()
                ->after('client_id')
                ->constrained('yarn_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->dropForeign(['yarn_type_id']);
            $table->dropColumn('yarn_type_id');
        });
    }
};
