<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Services\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionController extends Controller
{
    public function __construct(protected InspectionService $inspectionService) {}

    /** GET /api/admin/inspections/pending-release */
    public function pendingRelease(Request $request): JsonResponse
    {
        $inspections = Inspection::with(
            'roll.inspectionRequest.client',
            'roll.machine',
            'operator'
        )
            ->where('status', 'QC_VALIDATED')
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->successPaginated('Pending release inspections retrieved.', $inspections);
    }

    /** GET /api/admin/inspections/released */
    public function released(Request $request): JsonResponse
    {
        $inspections = Inspection::with(
            'roll.inspectionRequest.client',
            'roll.machine',
            'operator',
            'validator'
        )
            ->where('status', 'RELEASED')
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->successPaginated('Released inspections retrieved.', $inspections);
    }

    /** POST /api/admin/inspections/{inspection}/release */
    public function release(Request $request, Inspection $inspection): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return $this->error('Hanya admin yang dapat merilis hasil inspeksi.', null, 403);
        }

        try {
            $updated = $this->inspectionService->releaseInspection($inspection, $request->user()->id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success('Inspection released successfully.', $updated);
    }

    /** POST /api/admin/inspections/{inspection}/reject */
    public function reject(Request $request, Inspection $inspection): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return $this->error('Hanya admin yang dapat menolak hasil inspeksi.', null, 403);
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
        ]);

        try {
            $updated = $this->inspectionService->rejectInspectionByAdmin(
                $inspection,
                $request->user()->id,
                $request->input('reason')
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 409);
        }

        return $this->success('Inspection rejected back to QC.', $updated);
    }
}
