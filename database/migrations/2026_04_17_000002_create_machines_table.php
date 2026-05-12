<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('machine_name');
            $table->string('machine_type')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();

            $table->index('machine_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
