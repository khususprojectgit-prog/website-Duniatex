<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    /**
     * GET /api/admin/reports/summary
     * Query params: date_from, date_to, operator_id, machine_id
     */
    public function summary(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'operator_id', 'machine_id']);
        $data    = $this->reportService->inspectionSummary($filters);

        return $this->success('Inspection summary retrieved.', $data);
    }

    /**
     * GET /api/admin/reports/defect-analysis
     */
    public function defectAnalysis(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to']);
        $data    = $this->reportService->defectAnalysis($filters);

        return $this->success('Defect analysis retrieved.', $data);
    }

    /**
     * GET /api/admin/reports/operator-performance
     */
    public function operatorPerformance(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'operator_id']);
        $data    = $this->reportService->operatorPerformance($filters);

        return $this->success('Operator performance retrieved.', $data);
    }

    /**
     * GET /api/admin/reports/machine-issues
     */
    public function machineIssues(Request $request): JsonResponse
    {
        $filters = $request->only(['machine_id', 'date_from', 'date_to', 'status']);
        $data    = $this->reportService->machineIssueHistory($filters);

        return $this->success('Machine issue history retrieved.', $data);
    }

    /**
     * GET /api/admin/reports/export?format=pdf|excel&type=summary|defects|operators
     */
    public function export(Request $request): mixed
    {
        $request->validate([
            'format' => ['required', 'in:pdf,excel'],
            'type'   => ['required', 'in:summary,defects,operators,machine-issues'],
        ]);

        $filters = $request->only(['date_from', 'date_to', 'operator_id', 'machine_id']);
        $type    = $request->type;
        $format  = $request->format;

        $data = match ($type) {
            'summary'       => $this->reportService->inspectionSummary($filters),
            'defects'       => $this->reportService->defectAnalysis($filters)->toArray(),
            'operators'     => $this->reportService->operatorPerformance($filters)->toArray(),
            'machine-issues'=> $this->reportService->machineIssueHistory($filters)->toArray(),
        };

        if ($format === 'pdf') {
            $pdf = Pdf::loadView("reports.{$type}", compact('data', 'filters'));
            return $pdf->download("duniatex-{$type}-report.pdf");
        }

        // Excel — simple array export via collection
        return Excel::download(
            new \App\Exports\GenericArrayExport($data, $type),
            "duniatex-{$type}-report.xlsx"
        );
    }
}
