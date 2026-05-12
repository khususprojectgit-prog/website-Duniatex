<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('defect_type_id')->constrained('defect_types')->restrictOnDelete();
            $table->decimal('position_meter', 10, 2)->nullable();
            $table->unsignedInteger('point');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('inspection_id');
            $table->index('defect_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defects');
    }
};
