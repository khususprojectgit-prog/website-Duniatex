<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id', 'reported_by', 'issue_type', 'description', 'status',
    ];

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'OPEN');
    }

    // Relationships
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
