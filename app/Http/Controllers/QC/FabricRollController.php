<?php

namespace App\Http\Controllers\QC;

use App\Enums\FabricRollStatus;
use App\Enums\InspectionRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFabricRollRequest;
use App\Models\FabricRoll;
use App\Models\InspectionRequest;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FabricRollController extends Controller
{
    public function __construct(protected AuditService $audit) {}
    /** GET /api/qc/requests/{inspectionRequest}/rolls */
    public function index(InspectionRequest $inspectionRequest): JsonResponse
    {
        $rolls = $inspectionRequest->fabricRolls()
            ->with('machine', 'assignedOperator', 'latestInspection')
            ->orderBy('roll_code')
            ->get();

        return $this->success('Fabric rolls retrieved.', $rolls);
    }

    /** POST /api/qc/requests/{inspectionRequest}/rolls */
    public function store(StoreFabricRollRequest $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        // ❗ Jangan tambah roll kalau request sudah selesai
        if ($inspectionRequest->status === InspectionRequestStatus::COMPLETED) {
            return $this->error('Cannot add rolls to a completed request.');
        }

        $operator = \App\Models\User::where('id', $request->validated()['operator_id'])
            ->where('role', 'qc')
            ->where('status', 'active')
            ->first();

        if (! $operator) {
            return $this->error('QC tidak ditemukan atau tidak aktif.', null, 422);
        }

        $roll = DB::transaction(function () use ($request, $inspectionRequest) {

            $data = array_merge($request->validated(), [
                'request_id' => $inspectionRequest->id,
                'status'     => 'NEW',
            ]);

            $roll = FabricRoll::create($data);

            // ✅ Auto update request status
            if ($inspectionRequest->status === InspectionRequestStatus::NEW) {
                $inspectionRequest->update([
                    'status' => 'IN_PROGRESS'
                ]);
            }

            return $roll;
        });

        $this->audit->log(
            'roll_added',
            "Roll {$roll->roll_code} ditambahkan ke request {$inspectionRequest->request_code}",
            [
                'user_id'    => $request->user()->id,
                'roll_id'    => $roll->id,
                'request_id' => $inspectionRequest->id,
                'metadata'   => [
                    'roll_code'   => $roll->roll_code,
                    'operator_id' => $roll->operator_id,
                    'machine_id'  => $roll->machine_id,
                ],
            ]
        );

        if ($roll->machine_id) {
            $this->audit->log(
                'machine_assigned',
                "Mesin dialokasikan untuk roll {$roll->roll_code}",
                [
                    'user_id'    => $request->user()->id,
                    'roll_id'    => $roll->id,
                    'request_id' => $inspectionRequest->id,
                    'metadata'   => ['machine_id' => $roll->machine_id],
                ]
            );
        }

        return $this->success('Fabric roll added.', $roll->load('machine'), 201);
    }

    /** GET /api/qc/rolls/{fabricRoll} */
    public function show(FabricRoll $fabricRoll): JsonResponse
    {
        return $this->success(
            'Fabric roll retrieved.',
            $fabricRoll->load(
                'machine',
                'assignedOperator',
                'request.client',
                'latestInspection.defects.defectType',
                'latestInspection.operator'
            )
        );
    }

    /** PUT /api/qc/rolls/{fabricRoll} */
    public function update(StoreFabricRollRequest $request, FabricRoll $fabricRoll): JsonResponse
    {
        // ❗ Hanya boleh update kalau masih NEW / IN_PROGRESS
        if (!in_array($fabricRoll->status->value, ['NEW', 'IN_PROGRESS'])) {
            return $this->error('Only NEW or IN_PROGRESS rolls can be updated.');
        }

        $previousMachineId = $fabricRoll->machine_id;
        $fabricRoll->update($request->validated());

        // Log machine assignment when machine_id changes
        if (
            isset($request->validated()['machine_id']) &&
            $request->validated()['machine_id'] !== $previousMachineId
        ) {
            $this->audit->log(
                'machine_assigned',
                "Mesin diperbarui untuk roll {$fabricRoll->roll_code}",
                [
                    'user_id'  => $request->user()->id,
                    'roll_id'  => $fabricRoll->id,
                    'metadata' => [
                        'from_machine_id' => $previousMachineId,
                        'to_machine_id'   => $fabricRoll->machine_id,
                    ],
                ]
            );
        }

        return $this->success('Fabric roll updated.', $fabricRoll);
    }

    /** DELETE /api/qc/rolls/{fabricRoll} */
    public function destroy(FabricRoll $fabricRoll): JsonResponse
    {
        // ❗ Hanya boleh delete kalau masih NEW
        if ($fabricRoll->status !== FabricRollStatus::NEW) {
            return $this->error('Only NEW rolls can be deleted.');
        }

        $fabricRoll->delete();

        return $this->success('Fabric roll deleted.');
    }
}