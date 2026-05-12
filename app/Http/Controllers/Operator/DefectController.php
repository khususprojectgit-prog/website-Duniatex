<?php

namespace App\Http\Controllers\Operator;

use App\Enums\InspectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDefectRequest;
use App\Models\DefectType;
use App\Models\Inspection;
use App\Services\AuditService;
use App\Services\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DefectController extends Controller
{
    public function __construct(
        protected InspectionService $inspectionService,
        protected AuditService $audit,
    ) {}

    /** GET /api/operator/inspections/{inspection}/defects */
    public function index(Request $request, Inspection $inspection): JsonResponse
    {
        if ($inspection->operator_id !== $request->user()->id) {
            return $this->error('Forbidden.', null, 403);
        }

        return $this->success('Defects retrieved.',
            $inspection->defects()->with('defectType')->orderBy('position_meter')->get()
        );
    }

    /** GET /api/operator/defect-types */
    public function defectTypes(): JsonResponse
    {
        return $this->success('Defect types retrieved.', DefectType::orderBy('defect_name')->get());
    }

    /**
     * POST /api/operator/inspections/{inspection}/defects
     * Ownership + role check handled by StoreDefectRequest::authorize().
     */
    public function store(StoreDefectRequest $request, Inspection $inspection): JsonResponse
    {
        try {
            $defect = $this->inspectionService->addDefect($inspection, $request->validated());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        $this->audit->log(
            'defect_added',
            "Operator menambahkan cacat pada posisi {$defect->position_meter}m di roll {$inspection->roll->roll_code}",
            [
                'user_id'       => $request->user()->id,
                'inspection_id' => $inspection->id,
                'roll_id'       => $inspection->roll_id,
                'request_id'    => $inspection->roll->request_id ?? null,
                'metadata'      => [
                    'defect_type_id'  => $defect->defect_type_id,
                    'position_meter'  => $defect->position_meter,
                    'point'           => $defect->point,
                ],
            ]
        );

        return $this->success('Defect recorded.', $defect->load('defectType'), 201);
    }

    /** DELETE /api/operator/defects/{defect} */
    public function destroy(Request $request, \App\Models\Defect $defect): JsonResponse
    {
        $inspection = $defect->inspection;

        if ($inspection->operator_id !== $request->user()->id) {
            return $this->error('Forbidden.', null, 403);
        }

        if ($inspection->status !== InspectionStatus::IN_PROGRESS) {
            return $this->error('Cannot remove defects from a finished inspection.', null, 409);
        }

        $rollCode   = $inspection->roll->roll_code ?? 'N/A';
        $requestId  = $inspection->roll->request_id ?? null;
        $metadata   = [
            'defect_type_id' => $defect->defect_type_id,
            'position_meter' => $defect->position_meter,
            'point'          => $defect->point,
        ];

        $defect->delete();

        $this->audit->log(
            'defect_deleted',
            "Operator menghapus cacat pada posisi {$metadata['position_meter']}m di roll {$rollCode}",
            [
                'user_id'       => $request->user()->id,
                'inspection_id' => $inspection->id,
                'roll_id'       => $inspection->roll_id,
                'request_id'    => $requestId,
                'metadata'      => $metadata,
            ]
        );

        return $this->success('Defect removed.');
    }
}
