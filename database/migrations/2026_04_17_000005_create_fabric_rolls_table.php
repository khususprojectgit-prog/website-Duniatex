<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_rolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('inspection_requests')->restrictOnDelete();
            $table->string('roll_code')->unique();
            $table->decimal('length_meter', 10, 2);
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->string('batch_number')->nullable();
            $table->enum('status', ['NEW', 'IN_PROGRESS', 'SUBMITTED', 'VALIDATED'])->default('NEW');
            $table->timestamps();

            $table->index('status');
            $table->index('request_id');
            $table->index('machine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_rolls');
    }
};
