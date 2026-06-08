<?php

use App\Models\FabricRoll;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $operator = User::where('role', 'operator')->where('status', 'active')->orderBy('id')->first();

        if ($operator) {
            FabricRoll::whereNull('operator_id')->update(['operator_id' => $operator->id]);
        }
    }

    public function down(): void
    {
        // No rollback — assignment data is operational
    }
};
