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
        protected AuditService              $audit,
        protected StateMachineService       $sm,
        protected InspectionScoringService  $scoring,
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
    /**
     * @param  array{shift:string,length_meter:float|int|string,weight_kg:float|int|string,gramasi:float|int|string,yarn_name:string}  $measurements
     */
    public function startInspection(FabricRoll $roll, int $operatorId, array $measurements): Inspection
    {
        return DB::transaction(function () use ($roll, $operatorId, $measurements) {
            // Lock roll row — prevent concurrent starts on the same roll
            $lockedRoll = FabricRoll::lockForUpdate()->findOrFail($roll->id);



            if (Inspection::where('operator_id', $operatorId)
                ->where('status', InspectionStatus::IN_PROGRESS->value)
                ->exists()) {
                throw new DomainException('Selesaikan inspeksi yang sedang berjalan terlebih dahulu.');
            }

            // Validate roll transition: NEW|PENDING → IN_PROGRESS
            $this->sm->assertRollTransition($lockedRoll->status, FabricRollStatus::IN_PROGRESS->value);

            // Resolve Machine dynamically (if provided)
            $machine = null;
            if (!empty($measurements['machine_name'])) {
                $machine = \App\Models\Machine::firstOrCreate([
                    'machine_name' => trim($measurements['machine_name']),
                ]);
            }

            // Resolve QC User dynamically
            $qcName = trim($measurements['qc_name']);
            $qcEmail = strtolower(str_replace(' ', '', $qcName)) . '.qc@duniatex.com';
            $qc = \App\Models\User::firstOrCreate(
                ['email' => $qcEmail],
                [
                    'name'     => $qcName,
                    'role'     => 'qc',
                    'password' => bcrypt('password123'),
                    'status'   => 'active',
                ]
            );

            // Resolve Production Operator User dynamically
            $opName = trim($measurements['operator_name']);
            $opEmail = strtolower(str_replace(' ', '', $opName)) . '.op@duniatex.com';
            $productionOp = \App\Models\User::firstOrCreate(
                ['email' => $opEmail],
                [
                    'name'     => $opName,
                    'role'     => 'qc', // as requested, everyone is qc role now
                    'password' => bcrypt('password123'),
                    'status'   => 'active',
                ]
            );

            // Update Roll details: Machine, Lot (batch_number) & Assign production operator
            $lockedRoll->update([
                'status'       => FabricRollStatus::IN_PROGRESS->value,
                'batch_number' => isset($measurements['batch_number']) && $measurements['batch_number'] !== null ? trim($measurements['batch_number']) : $lockedRoll->batch_number,
                'machine_id'   => $machine?->id,
                'operator_id'  => $productionOp->id, // Production operator assigned here
            ]);

            // Sync InspectionRequest: NEW → IN_PROGRESS (only on first activation)
            $request = $lockedRoll->inspectionRequest;
            if ($request) {
                // Update QC supervisor user for the request
                $request->update(['qc_id' => $qc->id]);

                if ($request->status === InspectionRequestStatus::NEW) {
                    $this->sm->assertRequestTransition($request->status, InspectionRequestStatus::IN_PROGRESS->value);
                    $request->update(['status' => InspectionRequestStatus::IN_PROGRESS->value]);

                    Log::info('InspectionRequest activated', [
                        'request_id' => $request->id,
                        'from'       => InspectionRequestStatus::NEW->value,
                        'to'         => InspectionRequestStatus::IN_PROGRESS->value,
                    ]);
                }
            }

            $yarnName = trim($measurements['yarn_name'] ?? '');
            if (empty($yarnName) && $lockedRoll->inspectionRequest?->yarnType) {
                $yarnName = $lockedRoll->inspectionRequest->yarnType->yarn_name;
            }

            $inspection = Inspection::create([
                'roll_id'                  => $lockedRoll->id,
                'operator_id'              => $operatorId, // This is the Inspector (current user)
                'production_operator_name' => $opName,
                'shift'                    => $measurements['shift'],
                'length_meter'             => null, 
                'weight_kg'                => $measurements['weight_kg'] ?? null,
                'gramasi'                  => null, 
                'lebar'                    => null, 
                'yarn_name'                => $yarnName,
                'start_time'               => now(),
                'status'                   => InspectionStatus::IN_PROGRESS->value,
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
     * Finish an active inspection: calculate total_points, score, and grade.
     * Locks the inspection row to prevent concurrent finishes (double-click protection).
     *
     * Score formula:
     *   score = total_points × 1646 ÷ (length_meter × lebar)
     *   grade: ≤15 → A, >15 & <20 → B, ≥20 → BS
     *
     * @throws DomainException if inspection is not IN_PROGRESS
     */
    public function finishInspection(Inspection $inspection, array $data = []): Inspection
    {
        return DB::transaction(function () use ($inspection, $data) {
            // Lock inspection row — prevent race condition on concurrent requests
            $locked = Inspection::lockForUpdate()->findOrFail($inspection->id);

            // Validate transition: IN_PROGRESS → QC_VALIDATED
            $this->sm->assertInspectionTransition($locked->status, InspectionStatus::QC_VALIDATED->value);

            // Resolve Machine dynamically
            $machine = \App\Models\Machine::firstOrCreate([
                'machine_name' => trim($data['machine_name']),
            ]);

            // Store length_meter, weight_kg & gramasi now on the inspection & the fabric roll
            $locked->update([
                'length_meter'       => $data['length_meter'],
                'gramasi'            => $data['gramasi'],
                'lebar'              => $data['lebar'] ?? null,
                'weight_kg'          => $data['weight_kg'],
                'manual_roll_number' => $data['manual_roll_number'] ?? null,
                'potongan_1_kg'      => $data['potongan_1_kg'] ?? null,
                'potongan_2_kg'      => $data['potongan_2_kg'] ?? null,
                'keterangan_visual'  => $data['keterangan_visual'] ?? null,
                'catatan'            => $data['catatan'] ?? null,
            ]);
            $locked->roll->update([
                'length_meter' => $data['length_meter'],
                'machine_id'   => $machine->id,
                'batch_number' => trim($data['batch_number'] ?? $locked->roll->batch_number),
            ]);

            $totalPoints = (int) $locked->defects()->sum('point');
            $lengthMeter = (float) $locked->length_meter;
            $lebar       = (float) $locked->lebar;

            try {
                ['score' => $score, 'grade' => $grade] = $this->scoring->calculate(
                    $totalPoints,
                    $lengthMeter,
                    $lebar,
                );
            } catch (\InvalidArgumentException $e) {
                throw new DomainException($e->getMessage());
            }

            // Override grade if manual result is provided
            $grade = $data['result'] ?? $grade;

            $locked->update([
                'end_time'     => now(),
                'total_points' => $totalPoints,
                'score'        => $score,
                'result'       => $grade,
                'status'       => InspectionStatus::QC_VALIDATED->value,
            ]);

            // Sync roll: IN_PROGRESS → QC_VALIDATED
            $this->sm->assertRollTransition($locked->roll->status, FabricRollStatus::QC_VALIDATED->value);
            $locked->roll->update(['status' => FabricRollStatus::QC_VALIDATED->value]);

            // Atomic: check if ALL rolls in request are now finished (QC_VALIDATED or RELEASED)
            $parentRequest = $locked->roll->inspectionRequest;
            if ($parentRequest && $parentRequest->status !== \App\Enums\InspectionRequestStatus::COMPLETED) {
                // Check if any rolls are unfinished (i.e. NEW, IN_PROGRESS, or PENDING)
                $hasUnfinishedRolls = $parentRequest->fabricRolls()
                    ->whereIn('status', [
                        FabricRollStatus::NEW->value,
                        FabricRollStatus::IN_PROGRESS->value,
                        FabricRollStatus::PENDING->value
                    ])
                    ->exists();

                if (! $hasUnfinishedRolls) {
                    // Auto-heal / advance NEW → IN_PROGRESS if needed
                    if ($parentRequest->status === \App\Enums\InspectionRequestStatus::NEW) {
                        $parentRequest->update(['status' => \App\Enums\InspectionRequestStatus::IN_PROGRESS->value]);
                        $parentRequest->refresh();
                    }

                    $this->sm->assertRequestTransition(
                        $parentRequest->status,
                        \App\Enums\InspectionRequestStatus::COMPLETED->value
                    );
                    $parentRequest->update(['status' => \App\Enums\InspectionRequestStatus::COMPLETED->value]);

                    Log::info('InspectionRequest completed automatically on finishInspection', [
                        'request_id' => $parentRequest->id,
                        'from'       => $parentRequest->status,
                        'to'         => \App\Enums\InspectionRequestStatus::COMPLETED->value,
                    ]);

                    $this->audit->log(
                        'request_completed',
                        "Inspection request {$parentRequest->request_code} telah selesai — semua roll telah diselesaikan",
                        [
                            'user_id'    => null,
                            'request_id' => $parentRequest->id,
                            'metadata'   => [
                                'from'            => \App\Enums\InspectionRequestStatus::IN_PROGRESS->value,
                                'to'              => \App\Enums\InspectionRequestStatus::COMPLETED->value,
                            ],
                        ]
                    );
                }
            }

            Log::info('Inspection submitted', [
                'inspection_id' => $locked->id,
                'roll_code'     => $locked->roll->roll_code,
                'score'         => $score,
                'grade'         => $grade,
                'from'          => InspectionStatus::IN_PROGRESS->value,
                'to'            => InspectionStatus::QC_VALIDATED->value,
            ]);

            $this->audit->log(
                'inspection_submitted',
                "QC Inspector menyerahkan hasil inspeksi roll {$locked->roll->roll_code} — skor: {$score}, grade: {$grade}",
                [
                    'user_id'       => $locked->operator_id,
                    'inspection_id' => $locked->id,
                    'roll_id'       => $locked->roll_id,
                    'request_id'    => $locked->roll->request_id,
                    'metadata'      => [
                        'from'   => InspectionStatus::IN_PROGRESS->value,
                        'to'     => InspectionStatus::QC_VALIDATED->value,
                        'score'  => $score,
                        'grade'  => $grade,
                    ],
                ]
            );

            return $locked->fresh([
                'roll.machine',
                'roll.inspectionRequest.client',
                'roll.inspectionRequest.qc',
                'roll.inspectionRequest.setting',
                'defects.defectType',
            ]);
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
            'side'           => $data['side'],
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

            // Revert parent request back to IN_PROGRESS if it was COMPLETED
            $parentRequest = $lockedRoll->inspectionRequest;
            if ($parentRequest && $parentRequest->status === \App\Enums\InspectionRequestStatus::COMPLETED) {
                $this->sm->assertRequestTransition(
                    $parentRequest->status,
                    \App\Enums\InspectionRequestStatus::IN_PROGRESS->value
                );
                $parentRequest->update(['status' => \App\Enums\InspectionRequestStatus::IN_PROGRESS->value]);

                Log::info('InspectionRequest reverted to IN_PROGRESS on rejectInspection', [
                    'request_id' => $parentRequest->id,
                    'from'       => \App\Enums\InspectionRequestStatus::COMPLETED->value,
                    'to'         => \App\Enums\InspectionRequestStatus::IN_PROGRESS->value,
                ]);

                $this->audit->log(
                    'request_reopened',
                    "Inspection request {$parentRequest->request_code} dibuka kembali ke IN_PROGRESS karena roll {$lockedRoll->roll_code} ditolak oleh QC",
                    [
                        'user_id'    => $qcUserId,
                        'request_id' => $parentRequest->id,
                        'metadata'   => [
                            'from'    => \App\Enums\InspectionRequestStatus::COMPLETED->value,
                            'to'      => \App\Enums\InspectionRequestStatus::IN_PROGRESS->value,
                            'roll_id' => $lockedRoll->id,
                        ],
                    ]
                );
            }

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

    /**
     * Release an inspection to the customer (Admin action).
     *
     * @throws DomainException if not in QC_VALIDATED state
     */
    public function releaseInspection(Inspection $inspection, int $adminId): Inspection
    {
        return DB::transaction(function () use ($inspection, $adminId) {
            $locked = Inspection::lockForUpdate()->findOrFail($inspection->id);

            // Validate transition: QC_VALIDATED → RELEASED
            $this->sm->assertInspectionTransition($locked->status, InspectionStatus::RELEASED->value);

            $locked->update([
                'status'       => InspectionStatus::RELEASED->value,
                'validated_by' => $adminId,
                'validated_at' => now(),
            ]);

            $lockedRoll = $locked->roll()->lockForUpdate()->first();
            $this->sm->assertRollTransition($lockedRoll->status, FabricRollStatus::RELEASED->value);
            $lockedRoll->update(['status' => FabricRollStatus::RELEASED->value]);
            $locked->setRelation('roll', $lockedRoll);

            // Audit log
            $this->audit->log(
                'inspection_released',
                "Admin merilis hasil inspeksi roll {$lockedRoll->roll_code} ke customer",
                [
                    'user_id'       => $adminId,
                    'inspection_id' => $locked->id,
                    'roll_id'       => $lockedRoll->id,
                    'request_id'    => $lockedRoll->request_id,
                    'metadata'      => [
                        'from' => InspectionStatus::QC_VALIDATED->value,
                        'to'   => InspectionStatus::RELEASED->value,
                    ],
                ]
            );

            return $locked->fresh();
        });
    }

    /**
     * Reject an inspection back to QC (Admin action).
     *
     * @throws DomainException if not in QC_VALIDATED state
     */
    public function rejectInspectionByAdmin(Inspection $inspection, int $adminId, string $reason): Inspection
    {
        return DB::transaction(function () use ($inspection, $adminId, $reason) {
            $locked = Inspection::lockForUpdate()->findOrFail($inspection->id);

            // Validate transition: QC_VALIDATED → REJECTED
            $this->sm->assertInspectionTransition($locked->status, InspectionStatus::REJECTED->value);

            $locked->update([
                'status'           => InspectionStatus::REJECTED->value,
                'validated_by'     => $adminId,
                'validated_at'     => now(),
                'rejection_reason' => $reason,
            ]);

            $lockedRoll = $locked->roll()->lockForUpdate()->first();
            $this->sm->assertRollTransition($lockedRoll->status, FabricRollStatus::PENDING->value);
            $lockedRoll->update(['status' => FabricRollStatus::PENDING->value]);
            $locked->setRelation('roll', $lockedRoll);

            // Revert parent request back to IN_PROGRESS if it was COMPLETED
            $parentRequest = $lockedRoll->inspectionRequest;
            if ($parentRequest && $parentRequest->status === \App\Enums\InspectionRequestStatus::COMPLETED) {
                $this->sm->assertRequestTransition(
                    $parentRequest->status,
                    \App\Enums\InspectionRequestStatus::IN_PROGRESS->value
                );
                $parentRequest->update(['status' => \App\Enums\InspectionRequestStatus::IN_PROGRESS->value]);

                $this->audit->log(
                    'request_reopened',
                    "Inspection request {$parentRequest->request_code} dibuka kembali karena roll {$lockedRoll->roll_code} ditolak oleh Admin",
                    [
                        'user_id'    => $adminId,
                        'request_id' => $parentRequest->id,
                        'metadata'   => [
                            'from'    => \App\Enums\InspectionRequestStatus::COMPLETED->value,
                            'to'      => \App\Enums\InspectionRequestStatus::IN_PROGRESS->value,
                            'roll_id' => $lockedRoll->id,
                        ],
                    ]
                );
            }

            // Audit log
            $this->audit->log(
                'inspection_rejected_by_admin',
                "Admin menolak inspeksi roll {$lockedRoll->roll_code} — alasan: {$reason}",
                [
                    'user_id'       => $adminId,
                    'inspection_id' => $locked->id,
                    'roll_id'       => $lockedRoll->id,
                    'request_id'    => $lockedRoll->request_id,
                    'metadata'      => [
                        'from'   => InspectionStatus::QC_VALIDATED->value,
                        'to'     => InspectionStatus::REJECTED->value,
                        'reason' => $reason,
                    ],
                ]
            );

            return $locked->fresh();
        });
    }
}
