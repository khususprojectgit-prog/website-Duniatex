<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 100);        // e.g. INSPECTION_STARTED
            $table->text('description');           // human-readable detail
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — audit records are immutable
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
