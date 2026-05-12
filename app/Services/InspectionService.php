<?php

namespace App\Services;

use App\Enums\FabricRollStatus;
use App\Enums\InspectionRequestStatus;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\FabricRoll;
use App\Models\Inspection;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InspectionService
{
    public function __construct(
        protected AuditService        $audit,
        protected StateMachineService $sm,
    ) {}

    // -----------------------------------------------------------------------
    // Start Inspection
    // -----------------------------------------------------------------------

    /**
     * Start an inspection for a given fabric roll.
     * Accepts rolls in NEW or PENDING state (re-inspection after QC rejection).
     * Locks the roll row to prevent concurrent starts.
     *
     * Side-effects (all atomic):
     *   - roll:    NEW|PENDING → IN_PROGRESS
     *   - request: NEW → IN_PROGRESS  (only on first roll start)
     *   - creates Inspection record
     *
     * @throws DomainException on invalid state transition
     */
    public function startInspection(FabricRoll $roll, int $operatorId): Inspection
    {
        return DB::transaction(function () use ($roll, $operatorId) {
            // Lock roll row — prevent concurrent starts on the same roll
            $lockedRoll = FabricRoll::lockForUpdate()->findOrFail($roll->id);

            // Validate roll transition: NEW|PENDING → IN_PROGRESS
            $this->sm->assertRollTransition($lockedRoll->status, FabricRollStatus::IN_PROGRESS->value);

            $lockedRoll->update(['status' => FabricRollStatus::IN_PROGRESS->value]);

            // Sync InspectionRequest: NEW → IN_PROGRESS (only on first activation)
            $request = $lockedRoll->inspectionRequest;
            if ($request && $request->status === InspectionRequestStatus::NEW) {
                $this->sm->assertRequestTransition($request->status, InspectionRequestStatus::IN_PROGRESS->value);
                $request->update(['status' => InspectionRequestStatus::IN_PROGRESS->value]);

                Log::info('InspectionRequest activated', [
                    'request_id' => $request->id,
                    'from'       => InspectionRequestStatus::NEW->value,
                    'to'         => InspectionRequestStatus::IN_PROGRESS->value,
                ]);
            }

            $inspection = Inspection::create([
                'roll_id'     => $lockedRoll->id,
                'operator_id' => $operatorId,
                'start_time'  => now(),
                'status'      => InspectionStatus::IN_PROGRESS->value,
            ]);

            Log::info('Inspection started', [
                'inspection_id' => $inspection->id,
                'roll_code'     => $lockedRoll->roll_code,
                'roll_from'     => $roll->status instanceof \BackedEnum ? $roll->status->value : $roll->status,
                'roll_to'       => FabricRollStatus::IN_PROGRESS->value,
                'operator_id'   => $operatorId,
            ]);

            $fromStatus = $roll->status instanceof \BackedEnum
                ? $roll->status->value
                : (string) $roll->status;

            // Emit a single, context-appropriate audit event per start action.
            // Re-inspections (PENDING) use 'roll_reinspection_started' which already
            // carries full context; a redundant 'inspection_started' would create
            // timeline noise without adding information.
            if ($fromStatus === FabricRollStatus::PENDING->value) {
                // Re-inspection after QC rejection — one descriptive event is enough.
                $this->audit->log(
                    'roll_reinspection_started',
                    "Operator memulai ulang inspeksi roll {$lockedRoll->roll_code} setelah penolakan QC",
                    [
                        'user_id'       => $operatorId,
                        'inspection_id' => $inspection->id,
                        'roll_id'       => $lockedRoll->id,
                        'request_id'    => $lockedRoll->request_id,
                        'metadata'      => [
                            'from' => $fromStatus,
                            'to'   => FabricRollStatus::IN_PROGRESS->value,
                        ],
                    ]
                );
            } else {
                // First inspection — normal 'inspection_started' event.
                $this->audit->log(
                    'inspection_started',
                    "Operator memulai inspeksi roll {$lockedRoll->roll_code}",
                    [
                        'user_id'       => $operatorId,
                        'inspection_id' => $inspection->id,
                        'roll_id'       => $lockedRoll->id,
                        'request_id'    => $lockedRoll->request_id,
                        'metadata'      => [
                            'from' => $fromStatus,
                            'to'   => FabricRollStatus::IN_PROGRESS->value,
                        ],
                    ]
                );
            }

            return $inspection->load('roll.machine');
        });
    }

    // -----------------------------------------------------------------------
    // Finish (Submit) Inspection
    // -----------------------------------------------------------------------

    /**
     * Finish an active inspection: calculate total_points, score, and result.
     * Locks the inspection row to prevent concurrent finishes (double-click protection).
     *
     * Score formula (4-Point System, per 100 m):
     *   score  = (total_points / length_meter) × 100
     *   result = score ≤ 24 → PASS, else FAIL
     *
     * @throws DomainException if inspection is not IN_PROGRESS
     */
    public function finishInspection(Inspection $inspection): Inspection
    {
        return DB::transaction(function () use ($inspection) {
            // Lock inspection row — prevent race condition on concurrent requests
            $locked = Inspection::lockForUpdate()->findOrFail($inspection->id);

            // Validate transition: IN_PROGRESS → SUBMITTED
            $this->sm->assertInspectionTransition($locked->status, InspectionStatus::SUBMITTED->value);

            $totalPoints = $locked->defects()->sum('point');
            $lengthMeter = $locked->roll->length_meter;

            $score  = $lengthMeter > 0 ? round(($totalPoints / $lengthMeter) * 100, 2) : 0;
            $result = $score <= 24 ? 'PASS' : 'FAIL';

            $locked->update([
                'end_time'     => now(),
                'total_points' => $totalPoints,
                'score'        => $score,
                'result'       => $result,
                'status'       => InspectionStatus::SUBMITTED->value,
            ]);

            // Sync roll: IN_PROGRESS → SUBMITTED
            $this->sm->assertRollTransition($locked->roll->status, FabricRollStatus::SUBMITTED->value);
            $locked->roll->update(['status' => FabricRollStatus::SUBMITTED->value]);

            Log::info('Inspection submitted', [
                'inspection_id' => $locked->id,
                'roll_code'     => $locked->roll->roll_code,
                'score'         => $score,
                'result'        => $result,
                'from'          => InspectionStatus::IN_PROGRESS->value,
                'to'            => InspectionStatus::SUBMITTED->value,
            ]);

            $this->audit->log(
                'inspection_submitted',
                "Operator menyerahkan hasil inspeksi roll {$locked->roll->roll_code} — skor: {$score}, hasil: {$result}",
                [
                    'user_id'       => $locked->operator_id,
                    'inspection_id' => $locked->id,
                    'roll_id'       => $locked->roll_id,
                    'request_id'    => $locked->roll->request_id,
                    'metadata'      => [
                        'from'   => InspectionStatus::IN_PROGRESS->value,
                        'to'     => InspectionStatus::SUBMITTED->value,
                        'score'  => $score,
                        'result' => $result,
                    ],
                ]
            );

            return $locked->fresh(['roll', 'defects']);
        });
    }

    // -----------------------------------------------------------------------
    // Add Defect
    // -----------------------------------------------------------------------

    /**
     * Add a defect to an active inspection.
     *
     * @param  array  $data  Validated: defect_type_id, position_meter, point, notes
     * @throws DomainException if inspection is not IN_PROGRESS
     */
    public function addDefect(Inspection $inspection, array $data): Defect
    {
        if ($inspection->status !== InspectionStatus::IN_PROGRESS) {
            throw new DomainException(
                "Cannot add defect: inspection [{$inspection->id}] is not active (status: {$inspection->status->value})."
            );
        }

        return Defect::create([
            'inspection_id'  => $inspection->id,
            'defect_type_id' => $data['defect_type_id'],
            'position_meter' => $data['position_meter'],
            'point'          => $data['point'],
            'notes'          => $data['notes'] ?? null,
        ]);
    }

    // -----------------------------------------------------------------------
    // Validate Inspection (QC action)
    // -----------------------------------------------------------------------

    /**
     * Validate an inspection (QC action).
     * Atomically transitions inspection, roll, and (if complete) request.
     *
     * @throws DomainException if not in SUBMITTED state
     */
    public function validateInspection(Inspection $inspection, int $qcUserId): Inspection
    {
        return DB::transaction(function () use ($inspection, $qcUserId) {
            // Lock inspection row — prevent double-validation
            $locked = Inspection::lockForUpdate()->findOrFail($inspection->id);

            // Validate transition: SUBMITTED → VALIDATED
            $this->sm->assertInspectionTransition($locked->status, InspectionStatus::VALIDATED->value);

            $locked->update([
                'status'       => InspectionStatus::VALIDATED->value,
                'validated_by' => $qcUserId,
                'validated_at' => now(),
            ]);

            // Lock the roll row before updating to prevent a race condition if two QC
            // users concurrently validate different inspections on the same roll.
            // In practice there is only one active inspection per roll, but the lock
            // costs nothing and eliminates an edge-case phantom read.
            $lockedRoll = $locked->roll()->lockForUpdate()->first();
            $this->sm->assertRollTransition($lockedRoll->status, FabricRollStatus::VALIDATED->value);
            $lockedRoll->update(['status' => FabricRollStatus::VALIDATED->value]);
            $locked->setRelation('roll', $lockedRoll);

            // Atomic: check if ALL rolls in request are now VALIDATED → COMPLETED
            $parentRequest = $lockedRoll->inspectionRequest;
            if ($parentRequest && $parentRequest->status !== InspectionRequestStatus::COMPLETED) {
                $allValidated = $parentRequest->fabricRolls()
                    ->where('status', '!=', FabricRollStatus::VALIDATED->value)
                    ->doesntExist();

                if ($allValidated) {
                    // Auto-heal: requests may be stuck in NEW if startInspection's status
                    // transition was skipped due to a prior enum-comparison bug. Advance
                    // NEW → IN_PROGRESS first so the normal IN_PROGRESS → COMPLETED path works.
                    if ($parentRequest->status === InspectionRequestStatus::NEW) {
                        Log::warning('Auto-healing InspectionRequest stuck in NEW during validation', [
                            'request_id' => $parentRequest->id,
                        ]);
                        $parentRequest->update(['status' => InspectionRequestStatus::IN_PROGRESS->value]);
                        $parentRequest->refresh();
                    }

                    $this->sm->assertRequestTransition(
                        $parentRequest->status,
                        InspectionRequestStatus::COMPLETED->value
                    );
                    $parentRequest->update(['status' => InspectionRequestStatus::COMPLETED->value]);

                    Log::info('InspectionRequest completed', [
                        'request_id' => $parentRequest->id,
                        'from'       => InspectionRequestStatus::IN_PROGRESS->value,
                        'to'         => InspectionRequestStatus::COMPLETED->value,
                    ]);

                    // System event — no actor (null user_id)
                    $this->audit->log(
                        'request_completed',
                        "Inspection request {$parentRequest->request_code} telah selesai — semua roll telah divalidasi",
                        [
                            'user_id'    => null,
                            'request_id' => $parentRequest->id,
                            'metadata'   => [
                                'from'            => InspectionRequestStatus::IN_PROGRESS->value,
                                'to'              => InspectionRequestStatus::COMPLETED->value,
                                'triggered_by_qc' => $qcUserId,
                            ],
                        ]
                    );
                }
            }

            Log::info('Inspection validated', [
                'inspection_id' => $locked->id,
                'roll_code'     => $locked->roll->roll_code,
                'from'          => InspectionStatus::SUBMITTED->value,
                'to'            => InspectionStatus::VALIDATED->value,
                'validated_by'  => $qcUserId,
            ]);

            $this->audit->log(
                'inspection_validated',
                "QC memvalidasi inspeksi untuk roll {$locked->roll->roll_code}",
                [
                    'user_id'       => $qcUserId,
                    'inspection_id' => $locked->id,
                    'roll_id'       => $locked->roll_id,
                    'request_id'    => $locked->roll->request_id,
                    'metadata'      => [
                        'from' => InspectionStatus::SUBMITTED->value,
                        'to'   => InspectionStatus::VALIDATED->value,
                    ],
                ]
            );

            return $locked->fresh();
        });
    }

    // -----------------------------------------------------------------------
    // Reject Inspection (QC action)
    // -----------------------------------------------------------------------

    /**
     * Reject an inspection (QC action).
     * Roll is atomically set to PENDING for re-inspection by operator.
     *
     * @throws DomainException if not in SUBMITTED state
     */
    public function rejectInspection(Inspection $inspection, int $qcUserId, string $reason): Inspection
    {
        return DB::transaction(function () use ($inspection, $qcUserId, $reason) {
            // Lock inspection row — prevent double-rejection
            $locked = Inspection::lockForUpdate()->findOrFail($inspection->id);

            // Validate transition: SUBMITTED → REJECTED
            $this->sm->assertInspectionTransition($locked->status, InspectionStatus::REJECTED->value);

            $locked->update([
                'status'           => InspectionStatus::REJECTED->value,
                'validated_by'     => $qcUserId,
                'validated_at'     => now(),
                'rejection_reason' => $reason,
            ]);

            // Lock the roll row before updating — mirrors the lock in validateInspection()
            // to prevent concurrent QC operations on the same roll.
            $lockedRoll = $locked->roll()->lockForUpdate()->first();
            $this->sm->assertRollTransition($lockedRoll->status, FabricRollStatus::PENDING->value);
            $lockedRoll->update(['status' => FabricRollStatus::PENDING->value]);
            $locked->setRelation('roll', $lockedRoll);

            Log::info('Inspection rejected', [
                'inspection_id' => $locked->id,
                'roll_code'     => $locked->roll->roll_code,
                'from'          => InspectionStatus::SUBMITTED->value,
                'to'            => InspectionStatus::REJECTED->value,
                'roll_to'       => FabricRollStatus::PENDING->value,
                'reason'        => $reason,
                'rejected_by'   => $qcUserId,
            ]);

            $this->audit->log(
                'inspection_rejected',
                "QC menolak inspeksi untuk roll {$locked->roll->roll_code} — alasan: {$reason}",
                [
                    'user_id'       => $qcUserId,
                    'inspection_id' => $locked->id,
                    'roll_id'       => $locked->roll_id,
                    'request_id'    => $locked->roll->request_id,
                    'metadata'      => [
                        'from'   => InspectionStatus::SUBMITTED->value,
                        'to'     => InspectionStatus::REJECTED->value,
                        'reason' => $reason,
                    ],
                ]
            );

            return $locked->fresh();
        });
    }
}
