<?php

namespace App\Models;

use App\Enums\InspectionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'roll_id', 'operator_id', 'start_time', 'end_time',
        'total_points', 'score', 'result', 'status',
        'validated_by', 'validated_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_time'   => 'datetime',
            'end_time'     => 'datetime',
            'validated_at' => 'datetime',
            'score'        => 'decimal:2',
            'status'       => InspectionStatus::class,
        ];
    }

    // -----------------------------------------------------------------------
    // State helpers
    // -----------------------------------------------------------------------

    /** Defects may only be added while IN_PROGRESS. */
    public function isEditable(): bool
    {
        return $this->status === InspectionStatus::IN_PROGRESS;
    }

    /** No further QC action possible on VALIDATED or REJECTED inspections. */
    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    /** QC can validate or reject only SUBMITTED inspections. */
    public function isActionable(): bool
    {
        return $this->status === InspectionStatus::SUBMITTED;
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeSubmitted($query)
    {
        return $query->where('status', InspectionStatus::SUBMITTED->value);
    }

    public function scopeByOperator($query, int $operatorId)
    {
        return $query->where('operator_id', $operatorId);
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function roll()
    {
        return $this->belongsTo(FabricRoll::class, 'roll_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function defects()
    {
        return $this->hasMany(Defect::class);
    }
}
