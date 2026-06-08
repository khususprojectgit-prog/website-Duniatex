<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Http\Requests\QCValidateInspectionRequest;
use App\Models\Inspection;
use App\Services\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    public function __construct(protected InspectionService $inspectionService) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->status ?: 'SUBMITTED';

        $inspections = Inspection::with(
            'roll.inspectionRequest.client',
            'roll.machine',
            'operator'
        )
            ->when($status, function ($q) use ($status) {
                if ($status === 'SUBMITTED') {
                    $q->whereIn('status', ['SUBMITTED', 'VALIDATED']);
                } else {
                    $q->where('status', $status);
                }
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successPaginated('Inspections retrieved.', $inspections);
    }

    public function show(Inspection $inspection): JsonResponse
    {
        return $this->success(
            'Inspection retrieved.',
            $inspection->load(
                'roll.inspectionRequest.client',
                'roll.inspectionRequest.qc',
                'roll.inspectionRequest.setting',
                'roll.inspectionRequest.yarnType',
                'roll.machine',
                'operator',
                'validator',
                'defects.defectType'
            )
        );
    }

    /**
     * POST /api/qc/inspections/{inspection}/validate
     *
     * Atomically: inspection → VALIDATED, roll → VALIDATED,
     * and (if all rolls done) request → COMPLETED.
     * All sync now handled inside InspectionService::validateInspection().
     */
    public function validate(QCValidateInspectionRequest $request, Inspection $inspection): JsonResponse
    {
        try {
            $updated = $this->inspectionService->validateInspection(
                $inspection,
                $request->user()->id
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success(
            'Inspection validated successfully.',
            $updated->load('roll', 'defects')
        );
    }

    /**
     * POST /api/qc/inspections/{inspection}/reject
     *
     * Atomically: inspection → REJECTED, roll → PENDING (awaiting re-inspection).
     */
    public function reject(QCValidateInspectionRequest $request, Inspection $inspection): JsonResponse
    {
        try {
            $updated = $this->inspectionService->rejectInspection(
                $inspection,
                $request->user()->id,
                $request->validated()['reason']
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success(
            'Inspection rejected. Roll set to PENDING for re-inspection.',
            $updated
        );
    }
}