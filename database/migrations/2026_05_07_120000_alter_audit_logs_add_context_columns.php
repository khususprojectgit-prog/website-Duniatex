<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Make user_id nullable so system events (no actor) can be stored
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // Context FK columns — all nullable so any subset can be provided
            $table->unsignedBigInteger('inspection_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('roll_id')->nullable()->after('inspection_id');
            $table->unsignedBigInteger('request_id')->nullable()->after('roll_id');

            // Structured metadata (state transitions, reasons, scores, etc.)
            $table->json('metadata')->nullable()->after('description');

            // Performance indexes for timeline / audit queries
            $table->index('inspection_id', 'al_inspection_id_idx');
            $table->index('roll_id',        'al_roll_id_idx');
            $table->index('request_id',     'al_request_id_idx');
            $table->index('action',         'al_action_idx');
            $table->index('created_at',     'al_created_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign('inspection_id')
                  ->references('id')->on('inspections')->nullOnDelete();

            $table->foreign('roll_id')
                  ->references('id')->on('fabric_rolls')->nullOnDelete();

            $table->foreign('request_id')
                  ->references('id')->on('inspection_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['inspection_id']);
            $table->dropForeign(['roll_id']);
            $table->dropForeign(['request_id']);

            $table->dropIndex('al_inspection_id_idx');
            $table->dropIndex('al_roll_id_idx');
            $table->dropIndex('al_request_id_idx');
            $table->dropIndex('al_action_idx');
            $table->dropIndex('al_created_at_idx');

            $table->dropColumn(['inspection_id', 'roll_id', 'request_id', 'metadata']);

            // Restore user_id to non-nullable with original FK
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
