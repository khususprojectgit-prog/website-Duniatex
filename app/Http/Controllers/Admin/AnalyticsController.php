<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analytics) {}

    private function filters(Request $request): array
    {
        return $request->only([
            'date_from', 'date_to',
            'machine_id', 'client_id', 'operator_id', 'qc_id',
            'result', 'status', 'days',
        ]);
    }

    /** GET /api/admin/analytics/summary */
    public function summary(Request $request): JsonResponse
    {
        return $this->success('Analytics summary.', $this->analytics->summary($this->filters($request)));
    }

    /** GET /api/admin/analytics/trends */
    public function trends(Request $request): JsonResponse
    {
        return $this->success('Inspection trends.', $this->analytics->trends($this->filters($request)));
    }

    /** GET /api/admin/analytics/defects */
    public function defects(Request $request): JsonResponse
    {
        return $this->success('Defect analytics.', $this->analytics->defects($this->filters($request)));
    }

    /** GET /api/admin/analytics/machines */
    public function machines(Request $request): JsonResponse
    {
        return $this->success('Machine performance.', $this->analytics->machines($this->filters($request)));
    }

    /** GET /api/admin/analytics/inspections */
    public function inspections(Request $request): JsonResponse
    {
        $paginator = $this->analytics->inspections(
            $this->filters($request),
            (int) ($request->per_page ?? 20)
        );
        return $this->successPaginated('Inspections retrieved.', $paginator);
    }

    /** GET /api/admin/analytics/export/csv */
    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $rows     = $this->analytics->exportRows($this->filters($request));
        $filename = 'duniatex_inspections_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, [
                'ID', 'Roll Code', 'Client', 'Machine', 'Batch',
                'Operator', 'QC Validator', 'Total Points', 'Score',
                'Result', 'Status', 'Start Time', 'End Time', 'Created At',
            ]);
            foreach ($rows as $i) {
                $status = $i->status instanceof \BackedEnum ? $i->status->value : (string) $i->status;
                fputcsv($out, [
                    $i->id,
                    $i->roll->roll_code          ?? '-',
                    $i->roll->inspectionRequest->client->client_name ?? '-',
                    $i->roll->machine->machine_name ?? '-',
                    $i->roll->batch_number       ?? '-',
                    $i->operator->name            ?? '-',
                    $i->validator->name           ?? '-',
                    $i->total_points              ?? 0,
                    $i->score                     ?? 0,
                    $i->result                    ?? '-',
                    $status,
                    $i->start_time?->format('Y-m-d H:i:s') ?? '-',
                    $i->end_time?->format('Y-m-d H:i:s')   ?? '-',
                    $i->created_at?->format('Y-m-d H:i:s') ?? '-',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
