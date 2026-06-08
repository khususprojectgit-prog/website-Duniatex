<?php

namespace App\Models;

use App\Enums\FabricRollStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FabricRoll extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id', 'roll_code', 'length_meter', 'machine_id', 'operator_id', 'batch_number', 'status',
    ];

    protected function casts(): array
    {
        return [
            'length_meter' => 'decimal:2',
            'status'       => FabricRollStatus::class,
        ];
    }

    // -----------------------------------------------------------------------
    // State helpers
    // -----------------------------------------------------------------------

    /** Roll is available for an operator to start (or re-start) inspection. */
    public function isAvailable(): bool
    {
        return $this->status->isAvailable();
    }

    /** Roll has been validated — no further transitions possible. */
    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    /**
     * Rolls available for operator pickup: NEW (first inspection) or PENDING (re-inspection).
     */
    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [
            FabricRollStatus::NEW->value,
            FabricRollStatus::PENDING->value,
        ]);
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function inspectionRequest()
    {
        return $this->belongsTo(InspectionRequest::class, 'request_id');
    }

    /** Alias used by some older controller code — maps to inspectionRequest. */
    public function request()
    {
        return $this->inspectionRequest();
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function assignedOperator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function inspections()
    {
        return $this->hasMany(Inspection::class, 'roll_id');
    }

    /** Latest inspection attempt for this roll (may be IN_PROGRESS, SUBMITTED, etc.). */
    public function latestInspection()
    {
        return $this->hasOne(Inspection::class, 'roll_id')->latestOfMany();
    }

    /**
     * Prefer validated inspection for reporting; falls back to latest if none validated yet.
     */
    public function displayInspection()
    {
        return $this->hasOne(Inspection::class, 'roll_id')
            ->ofMany(
                ['validated_at' => 'max', 'id' => 'max'],
                fn ($q) => $q->where('status', 'VALIDATED')
            );
    }

    /** @deprecated Use latestInspection or displayInspection */
    public function inspection()
    {
        return $this->latestInspection();
    }
}
