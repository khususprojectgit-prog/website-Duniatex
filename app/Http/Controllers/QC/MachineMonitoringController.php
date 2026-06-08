<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MachineMonitoringController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    /**
     * GET /api/qc/machines/monitoring
     * Returns machine monitoring details.
     */
    public function index(Request $request): JsonResponse
    {
        $monitoring = $this->reportService->machineMonitoring();
        return $this->success('Machine monitoring data retrieved.', $monitoring);
    }
}
