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
            ->whereIn('status', ['VALIDATED', 'REJECTED', 'SUBMITTED', 'QC_VALIDATED', 'RELEASED'])
            ->with('roll.request.client', 'operator');

        $this->applyInspectionFilters($query, $filters);

        $inspections = $query->get();

        $total = $inspections->count();
        $gradeA  = $inspections->where('result', 'A')->count();
        $gradeB  = $inspections->where('result', 'B')->count();
        $gradeBs = $inspections->where('result', 'BS')->count();

        return [
            'total_inspections' => $total,
            'grade_a_count'     => $gradeA,
            'grade_b_count'     => $gradeB,
            'grade_bs_count'    => $gradeBs,
            'grade_a_rate'      => $total > 0 ? round(($gradeA / $total) * 100, 2) : 0,
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
                DB::raw("SUM(CASE WHEN result = 'A' THEN 1 ELSE 0 END) as grade_a"),
                DB::raw("SUM(CASE WHEN result = 'B' THEN 1 ELSE 0 END) as grade_b"),
                DB::raw("SUM(CASE WHEN result = 'BS' THEN 1 ELSE 0 END) as grade_bs"),
                DB::raw('AVG(score) as avg_score')
            )
            ->whereIn('status', ['VALIDATED', 'SUBMITTED', 'QC_VALIDATED', 'RELEASED'])
            ->with('operator')
            ->groupBy('operator_id');

        $this->applyInspectionFilters($query, $filters);

        return $query->get()->map(fn ($row) => [
            'operator_id'   => $row->operator_id,
            'operator_name' => $row->operator?->name ?? 'N/A',
            'total'         => $row->total,
            'grade_a'       => $row->grade_a,
            'grade_b'       => $row->grade_b,
            'grade_bs'      => $row->grade_bs,
            'grade_a_rate'  => $row->total > 0 ? round(($row->grade_a / $row->total) * 100, 2) : 0,
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

    /**
     * Machine Monitoring: active inspections, status, and issue counts.
     */
    public function machineMonitoring(): Collection
    {
        $machines = \App\Models\Machine::withCount(['machineIssues as active_issues_count' => function ($q) {
            $q->where('status', 'OPEN');
        }])
            ->get();

        return $machines->map(function ($machine) {
            // Find active inspection (IN_PROGRESS) on this machine
            $activeInspection = Inspection::whereHas('roll', function ($q) use ($machine) {
                $q->where('machine_id', $machine->id);
            })
                ->where('status', \App\Enums\InspectionStatus::IN_PROGRESS->value)
                ->with('roll.inspectionRequest.client', 'operator')
                ->latest()
                ->first();

            // Find last resolved/completed inspection on this machine
            $lastInspection = Inspection::whereHas('roll', function ($q) use ($machine) {
                $q->where('machine_id', $machine->id);
            })
                ->where('status', '!=', \App\Enums\InspectionStatus::IN_PROGRESS->value)
                ->with('roll.inspectionRequest.client')
                ->latest()
                ->first();

            return [
                'machine_id'          => $machine->id,
                'machine_name'        => $machine->machine_name,
                'status'              => $activeInspection ? 'RUNNING' : 'IDLE',
                'active_issues'       => $machine->active_issues_count,
                'active_inspection'   => $activeInspection ? [
                    'inspection_id' => $activeInspection->id,
                    'roll_code'     => $activeInspection->roll?->roll_code,
                    'operator'      => $activeInspection->operator?->name,
                    'client'        => $activeInspection->roll?->inspectionRequest?->client?->client_name,
                    'opk'           => $activeInspection->roll?->inspectionRequest?->opk,
                ] : null,
                'last_inspection'     => $lastInspection ? [
                    'inspection_id' => $lastInspection->id,
                    'roll_code'     => $lastInspection->roll?->roll_code,
                    'result'        => $lastInspection->result,
                    'score'         => $lastInspection->score,
                ] : null,
            ];
        });
    }
}
