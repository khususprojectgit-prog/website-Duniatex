<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained('machines')->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->string('issue_type');
            $table->text('description');
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'RESOLVED'])->default('OPEN');
            $table->timestamps();

            $table->index('machine_id');
            $table->index('status');
            $table->index('reported_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_issues');
    }
};
