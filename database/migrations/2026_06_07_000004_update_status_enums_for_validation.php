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

        // Add QC_VALIDATED and RELEASED to fabric_rolls.status ENUM
        DB::statement("
            ALTER TABLE fabric_rolls
            MODIFY COLUMN status ENUM('NEW','IN_PROGRESS','SUBMITTED','VALIDATED','PENDING','QC_VALIDATED','RELEASED')
            NOT NULL DEFAULT 'NEW'
        ");

        // Add QC_VALIDATED and RELEASED to inspections.status ENUM
        DB::statement("
            ALTER TABLE inspections
            MODIFY COLUMN status ENUM('IN_PROGRESS','SUBMITTED','VALIDATED','REJECTED','QC_VALIDATED','RELEASED')
            NOT NULL DEFAULT 'IN_PROGRESS'
        ");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Revert columns back to original enums
        DB::statement("
            ALTER TABLE fabric_rolls
            MODIFY COLUMN status ENUM('NEW','IN_PROGRESS','SUBMITTED','VALIDATED','PENDING')
            NOT NULL DEFAULT 'NEW'
        ");

        DB::statement("
            ALTER TABLE inspections
            MODIFY COLUMN status ENUM('IN_PROGRESS','SUBMITTED','VALIDATED','REJECTED')
            NOT NULL DEFAULT 'IN_PROGRESS'
        ");
    }
};
