<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** Only created_at is stored — audit records are immutable. */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'inspection_id',
        'roll_id',
        'request_id',
        'action',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'metadata'   => 'array',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function roll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class, 'roll_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(InspectionRequest::class, 'request_id');
    }

    // -----------------------------------------------------------------------
    // Timeline helper
    // -----------------------------------------------------------------------

    /**
     * Ordered, eager-loaded timeline for a specific inspection.
     *
     * Returns all audit entries linked to the given inspection_id, sorted
     * oldest-first (ASC) to match the natural workflow progression:
     *   Start → Defects → Submit → Validate/Reject → Re-inspect → …
     *
     * This is an intentional UX decision for a WORKFLOW TIMELINE (chronological),
     * NOT a log console (where DESC / newest-first is conventional).
     * Do not change to DESC without updating the frontend timeline component.
     */
    public static function timelineForInspection(int $inspectionId): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['user', 'inspection', 'roll'])
            ->where('inspection_id', $inspectionId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    // -----------------------------------------------------------------------
    // UI helpers — label & icon
    // -----------------------------------------------------------------------

    /**
     * Human-readable label derived from the action key.
     * Consumed by the frontend to display event titles in the timeline.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'inspection_started'        => 'Inspection Started',
            'inspection_submitted'      => 'Inspection Submitted',
            'inspection_validated'      => 'Inspection Validated',
            'inspection_rejected'       => 'Inspection Rejected',
            'defect_added'              => 'Defect Added',
            'defect_updated'            => 'Defect Updated',
            'defect_deleted'            => 'Defect Deleted',
            'request_created'           => 'Request Created',
            'request_completed'         => 'Request Completed',
            'roll_added'                => 'Roll Added',
            'machine_assigned'          => 'Machine Assigned',
            'roll_reinspection_started' => 'Re-Inspection Started',
            'defect_type_created'       => 'Defect Type Created',
            'client_created'            => 'Client Created',
            default                     => ucwords(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Icon token for frontend rendering.
     *
     * Mapping (token → suggested icon):
     *   play     → inspection_started / roll_reinspection_started
     *   upload   → inspection_submitted
     *   check    → inspection_validated
     *   x        → inspection_rejected
     *   flag     → request_completed
     *   plus     → defect_added / roll_added
     *   trash    → defect_deleted
     *   file-plus→ request_created / defect_type_created / client_created
     *   cpu      → machine_assigned
     *   activity → fallback
     */
    public function getActionIconAttribute(): string
    {
        return match (true) {
            str_contains($this->action, 'started')   => 'play',
            str_contains($this->action, 'submitted')  => 'upload',
            str_contains($this->action, 'validated')  => 'check',
            str_contains($this->action, 'rejected')   => 'x',
            str_contains($this->action, 'completed')  => 'flag',
            str_contains($this->action, 'assigned')   => 'cpu',
            $this->action === 'defect_added'           => 'plus',
            $this->action === 'roll_added'             => 'plus',
            str_contains($this->action, 'deleted')    => 'trash',
            str_contains($this->action, 'created')    => 'file-plus',
            default                                   => 'activity',
        };
    }
}
