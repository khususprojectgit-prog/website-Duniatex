<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Add PENDING to fabric_rolls.status ENUM
        DB::statement("
            ALTER TABLE fabric_rolls
            MODIFY COLUMN status ENUM('NEW','IN_PROGRESS','SUBMITTED','VALIDATED','PENDING')
            NOT NULL DEFAULT 'NEW'
        ");

        // Add IN_PROGRESS and COMPLETED to inspection_requests.status ENUM
        // (ensure all needed values exist)
        DB::statement("
            ALTER TABLE inspection_requests
            MODIFY COLUMN status ENUM('NEW','IN_PROGRESS','COMPLETED')
            NOT NULL DEFAULT 'NEW'
        ");

        // Ensure inspections.status ENUM is complete
        DB::statement("
            ALTER TABLE inspections
            MODIFY COLUMN status ENUM('IN_PROGRESS','SUBMITTED','VALIDATED','REJECTED')
            NOT NULL DEFAULT 'IN_PROGRESS'
        ");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Remove PENDING — convert any PENDING rows back to NEW first
        DB::statement("UPDATE fabric_rolls SET status = 'NEW' WHERE status = 'PENDING'");
        DB::statement("
            ALTER TABLE fabric_rolls
            MODIFY COLUMN status ENUM('NEW','IN_PROGRESS','SUBMITTED','VALIDATED')
            NOT NULL DEFAULT 'NEW'
        ");

        DB::statement("
            ALTER TABLE inspection_requests
            MODIFY COLUMN status ENUM('NEW','COMPLETED')
            NOT NULL DEFAULT 'NEW'
        ");
    }
};
