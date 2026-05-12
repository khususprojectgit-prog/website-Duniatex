<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QCReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    /**
     * GET /api/qc/reports/summary
     * QC sees their own requests only.
     */
    public function summary(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'machine_id']);
        // Scope to inspections on rolls belonging to this QC's requests
        $filters['qc_id'] = $request->user()->id;

        return $this->success('QC inspection summary retrieved.', $this->reportService->inspectionSummary($filters));
    }

    /**
     * GET /api/qc/reports/defect-analysis
     */
    public function defectAnalysis(Request $request): JsonResponse
    {
        return $this->success('Defect analysis retrieved.',
            $this->reportService->defectAnalysis($request->only(['date_from', 'date_to']))
        );
    }

    /**
     * GET /api/qc/reports/machine-issues
     */
    public function machineIssues(Request $request): JsonResponse
    {
        $filters = $request->only(['machine_id', 'date_from', 'date_to', 'status']);

        return $this->success('Machine issue history retrieved.', $this->reportService->machineIssueHistory($filters));
    }
}
