<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('qc_id')->constrained('users')->restrictOnDelete();
            $table->date('request_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['NEW', 'IN_PROGRESS', 'COMPLETED'])->default('NEW');
            $table->timestamps();

            $table->index('status');
            $table->index('request_date');
            $table->index('qc_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_requests');
    }
};
