<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yarn_types', function (Blueprint $table) {
            $table->id();
            $table->string('yarn_name', 100)->unique();
            $table->string('material', 100)->nullable();   // e.g. Cotton, Polyester, Nylon
            $table->string('count', 50)->nullable();       // e.g. 30s, 40s, 60s
            $table->string('color', 80)->nullable();       // e.g. Putih, Krem, Warna
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yarn_types');
    }
};
