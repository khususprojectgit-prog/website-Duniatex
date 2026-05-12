<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    // Relationships
    public function inspectionRequests()
    {
        return $this->hasMany(InspectionRequest::class, 'qc_id');
    }

    public function inspections()
    {
        return $this->hasMany(Inspection::class, 'operator_id');
    }

    public function validatedInspections()
    {
        return $this->hasMany(Inspection::class, 'validated_by');
    }

    public function machineIssues()
    {
        return $this->hasMany(MachineIssue::class, 'reported_by');
    }

    public function isAdmin(): bool    { return $this->role === 'admin'; }
    public function isQC(): bool       { return $this->role === 'qc'; }
    public function isOperator(): bool { return $this->role === 'operator'; }
}
