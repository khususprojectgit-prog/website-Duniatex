<?php

namespace App\Http\Controllers\Operator;

use App\Enums\FabricRollStatus;
use App\Enums\InspectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinishInspectionRequest;
use App\Http\Requests\StartInspectionRequest;
use App\Models\FabricRoll;
use App\Models\Inspection;
use App\Services\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperatorInspectionController extends Controller
{
    public function __construct(protected InspectionService $inspectionService) {}

    /**
     * GET /api/operator/rolls
     * Returns rolls available for operator pickup: NEW (first inspection) or PENDING (re-inspection).
     */
    public function availableRolls(Request $request): JsonResponse
    {
        $rolls = FabricRoll::with('machine', 'inspectionRequest.client')
            ->whereIn('status', [
                FabricRollStatus::NEW->value,
                FabricRollStatus::PENDING->value,
            ])
            ->orderByRaw("FIELD(status, ?, ?)", [
                FabricRollStatus::PENDING->value,  // PENDING first (urgent re-inspections)
                FabricRollStatus::NEW->value,
            ])
            ->orderBy('roll_code')
            ->paginate(20);

        return $this->successPaginated('Available rolls retrieved.', $rolls);
    }

    /**
     * POST /api/operator/rolls/{fabricRoll}/start
     * State guard (NEW|PENDING) is enforced in StartInspectionRequest::authorize().
     */
    public function start(StartInspectionRequest $request, FabricRoll $fabricRoll): JsonResponse
    {
        try {
            $inspection = $this->inspectionService->startInspection($fabricRoll, $request->user()->id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success('Inspection started.', $inspection->load('roll.machine'), 201);
    }

    /** GET /api/operator/inspections */
    public function myInspections(Request $request): JsonResponse
    {
        $inspections = Inspection::with('roll.machine', 'roll.inspectionRequest.client')
            ->where('operator_id', $request->user()->id)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successPaginated('Your inspections retrieved.', $inspections);
    }

    /** GET /api/operator/inspections/{inspection} */
    public function show(Request $request, Inspection $inspection): JsonResponse
    {
        if ($inspection->operator_id !== $request->user()->id) {
            return $this->error('Forbidden.', null, 403);
        }

        return $this->success(
            'Inspection retrieved.',
            $inspection->load('roll.machine', 'roll.inspectionRequest.client', 'defects.defectType')
        );
    }

    /**
     * POST /api/operator/inspections/{inspection}/finish
     * State + ownership guard enforced in FinishInspectionRequest::authorize().
     */
    public function finish(FinishInspectionRequest $request, Inspection $inspection): JsonResponse
    {
        try {
            $finished = $this->inspectionService->finishInspection($inspection);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success('Inspection submitted for QC validation.', $finished);
    }
}
