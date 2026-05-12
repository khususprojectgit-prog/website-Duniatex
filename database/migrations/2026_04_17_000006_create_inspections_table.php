<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roll_id')->constrained('fabric_rolls')->restrictOnDelete();
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->unsignedInteger('total_points')->default(0);
            $table->decimal('score', 8, 2)->default(0);
            $table->enum('result', ['PASS', 'FAIL'])->nullable();
            $table->enum('status', ['IN_PROGRESS', 'SUBMITTED', 'VALIDATED', 'REJECTED'])->default('IN_PROGRESS');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('operator_id');
            $table->index('result');
            $table->index('start_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
