<?php

namespace App\Services;

use App\Models\Defect;
use App\Models\Inspection;
use App\Models\MachineIssue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Inspection summary: total, pass, fail, pass rate grouped by date range.
     *
     * Supported filters: date_from, date_to, operator_id, machine_id
     */
    public function inspectionSummary(array $filters = []): array
    {
        $query = Inspection::query()
            ->whereIn('status', ['VALIDATED', 'REJECTED', 'SUBMITTED'])
            ->with('roll.request.client', 'operator');

        $this->applyInspectionFilters($query, $filters);

        $inspections = $query->get();

        $total = $inspections->count();
        $pass  = $inspections->where('result', 'PASS')->count();
        $fail  = $inspections->where('result', 'FAIL')->count();

        return [
            'total_inspections' => $total,
            'passed'            => $pass,
            'failed'            => $fail,
            'pass_rate'         => $total > 0 ? round(($pass / $total) * 100, 2) : 0,
            'avg_score'         => round($inspections->avg('score') ?? 0, 2),
            'filters_applied'   => $filters,
        ];
    }

    /**
     * Defect analysis: most frequent defect types by count and total points.
     */
    public function defectAnalysis(array $filters = []): Collection
    {
        $query = Defect::query()
            ->select(
                'defect_type_id',
                DB::raw('COUNT(*) as total_occurrences'),
                DB::raw('SUM(point) as total_points'),
                DB::raw('AVG(point) as avg_point')
            )
            ->with('defectType')
            ->groupBy('defect_type_id')
            ->orderByDesc('total_occurrences');

        if (! empty($filters['date_from'])) {
            $query->whereHas('inspection', fn ($q) => $q->where('start_time', '>=', $filters['date_from']));
        }
        if (! empty($filters['date_to'])) {
            $query->whereHas('inspection', fn ($q) => $q->where('start_time', '<=', $filters['date_to']));
        }

        return $query->get()->map(fn ($row) => [
            'defect_type'       => $row->defectType?->defect_name ?? 'Unknown',
            'default_point'     => $row->defectType?->default_point,
            'total_occurrences' => $row->total_occurrences,
            'total_points'      => $row->total_points,
            'avg_point'         => round($row->avg_point, 2),
        ]);
    }

    /**
     * Operator performance: inspections done, pass/fail, avg score per operator.
     */
    public function operatorPerformance(array $filters = []): Collection
    {
        $query = Inspection::query()
            ->select(
                'operator_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN result = 'PASS' THEN 1 ELSE 0 END) as passed"),
                DB::raw("SUM(CASE WHEN result = 'FAIL' THEN 1 ELSE 0 END) as failed"),
                DB::raw('AVG(score) as avg_score')
            )
            ->whereIn('status', ['VALIDATED', 'SUBMITTED'])
            ->with('operator')
            ->groupBy('operator_id');

        $this->applyInspectionFilters($query, $filters);

        return $query->get()->map(fn ($row) => [
            'operator_id'   => $row->operator_id,
            'operator_name' => $row->operator?->name ?? 'N/A',
            'total'         => $row->total,
            'passed'        => $row->passed,
            'failed'        => $row->failed,
            'pass_rate'     => $row->total > 0 ? round(($row->passed / $row->total) * 100, 2) : 0,
            'avg_score'     => round($row->avg_score ?? 0, 2),
        ]);
    }

    /**
     * Machine issue history with machine info and reporter.
     */
    public function machineIssueHistory(array $filters = []): Collection
    {
        $query = MachineIssue::query()
            ->with('machine', 'reporter')
            ->orderByDesc('created_at');

        if (! empty($filters['machine_id'])) {
            $query->where('machine_id', $filters['machine_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function applyInspectionFilters($query, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->where('start_time', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('start_time', '<=', $filters['date_to']);
        }
        if (! empty($filters['operator_id'])) {
            $query->where('operator_id', $filters['operator_id']);
        }
        if (! empty($filters['machine_id'])) {
            $query->whereHas('roll', fn ($q) => $q->where('machine_id', $filters['machine_id']));
        }
    }
}
