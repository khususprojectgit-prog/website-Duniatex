<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_name', 'machine_type', 'location',
    ];

    public function fabricRolls()
    {
        return $this->hasMany(FabricRoll::class);
    }

    public function machineIssues()
    {
        return $this->hasMany(MachineIssue::class);
    }
}
