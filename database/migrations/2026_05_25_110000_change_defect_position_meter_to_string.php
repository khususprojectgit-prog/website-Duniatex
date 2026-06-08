<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defects', function (Blueprint $table) {
            $table->string('position_meter', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('defects', function (Blueprint $table) {
            $table->decimal('position_meter', 10, 2)->nullable()->change();
        });
    }
};
