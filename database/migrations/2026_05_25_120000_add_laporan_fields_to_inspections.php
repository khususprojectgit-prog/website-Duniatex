<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->decimal('potongan_kg', 10, 2)->nullable()->after('lebar');
            $table->text('keterangan_visual')->nullable()->after('potongan_kg');
            $table->text('catatan')->nullable()->after('keterangan_visual');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn(['potongan_kg', 'keterangan_visual', 'catatan']);
        });
    }
};
