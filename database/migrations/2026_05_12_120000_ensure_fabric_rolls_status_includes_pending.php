<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Safety net when the DB ran an older ENUM without PENDING — inserts reject/skip PENDING → MySQL 1265.
 * Skips if INFORMATION_SCHEMA shows PENDING already (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $schema = DB::connection()->getDatabaseName();
        $row    = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$schema, 'fabric_rolls', 'status']
        );

        if (! $row || empty($row->COLUMN_TYPE)) {
            return;
        }

        if (stripos((string) $row->COLUMN_TYPE, 'pending') !== false) {
            return;
        }

        DB::statement("
            ALTER TABLE fabric_rolls
            MODIFY COLUMN status ENUM('NEW','IN_PROGRESS','SUBMITTED','VALIDATED','PENDING')
            NOT NULL DEFAULT 'NEW'
        ");
    }

    public function down(): void
    {
        // Do not shrink ENUM on rollback — avoids data-loss if PENDING rows exist.
    }
};
