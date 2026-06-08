<?php

namespace App\Services;

use App\Models\Defect;
use App\Models\Inspection;
use App\Models\InspectionRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    // ── Filter builder ──────────────────────────────────────────────────────

    private function baseInspectionQuery(array $f = [])
    {
        $q = Inspection::query();

        if (!empty($f['date_from'])) $q->whereDate('inspections.created_at', '>=', $f['date_from']);
        if (!empty($f['date_to']))   $q->whereDate('inspections.created_at', '<=', $f['date_to']);
        if (!empty($f['operator_id'])) $q->where('operator_id', $f['operator_id']);
        if (!empty($f['qc_id']))    $q->where('validated_by', $f['qc_id']);
        if (!empty($f['result']))   $q->where('result', strtoupper($f['result']));
        if (!empty($f['status']))   $q->where('status', strtoupper($f['status']));

        if (!empty($f['machine_id'])) {
            $q->whereHas('roll', fn($r) => $r->where('machine_id', $f['machine_id']));
        }
        if (!empty($f['client_id'])) {
            $q->whereHas('roll.inspectionRequest', fn($r) => $r->where('client_id', $f['client_id']));
        }
        if (!empty($f['opk'])) {
            $q->whereHas('roll.inspectionRequest', fn($r) => $r->where('opk', 'like', '%' . $f['opk'] . '%'));
        }

        return $q;
    }

    // ── KPI Summary ────────────────────────────────────────────────────────

    public function summary(array $f = []): array
    {
        $q = $this->baseInspectionQuery($f);

        $total  = (clone $q)->count();
        $gradeA = (clone $q)->where('result', 'A')->count();
        $gradeB = (clone $q)->where('result', 'B')->count();
        $gradeBs = (clone $q)->where('result', 'BS')->count();
        $pendingQC  = (clone $q)->where('status', 'SUBMITTED')->count();
        $avgScore   = (clone $q)->whereNotNull('score')->avg('score') ?? 0;

        // Reinspection = rolls inspected more than once
        $reinspections = DB::table('inspections')
            ->select('roll_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('roll_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()->count();

        // Total defects (optionally scoped to date range)
        $dq = Defect::query();
        if (!empty($f['date_from'])) {
            $dq->whereHas('inspection', fn($q) => $q->whereDate('created_at', '>=', $f['date_from']));
        }
        if (!empty($f['date_to'])) {
            $dq->whereHas('inspection', fn($q) => $q->whereDate('created_at', '<=', $f['date_to']));
        }
        $totalDefects = $dq->count();

        // Completed requests
        $rq = InspectionRequest::where('status', 'COMPLETED');
        if (!empty($f['date_from'])) $rq->whereDate('created_at', '>=', $f['date_from']);
        if (!empty($f['date_to']))   $rq->whereDate('created_at', '<=', $f['date_to']);

        return [
            'total_inspections'  => $total,
            'grade_a_count'      => $gradeA,
            'grade_b_count'      => $gradeB,
            'grade_bs_count'     => $gradeBs,
            'grade_a_rate'       => $total > 0 ? round($gradeA / $total * 100, 1) : 0,
            'grade_b_rate'       => $total > 0 ? round($gradeB / $total * 100, 1) : 0,
            'grade_bs_rate'      => $total > 0 ? round($gradeBs / $total * 100, 1) : 0,
            'avg_score'          => round((float) $avgScore, 2),
            'total_defects'      => $totalDefects,
            'pending_qc'         => $pendingQC,
            'completed_requests' => $rq->count(),
            'reinspection_count' => $reinspections,
        ];
    }

    // ── Daily Trend ────────────────────────────────────────────────────────

    public function trends(array $f = []): array
    {
        $days = min((int) ($f['days'] ?? 30), 90);

        return Inspection::query()
            ->selectRaw("DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN result='A' THEN 1 ELSE 0 END) as grade_a,
                SUM(CASE WHEN result='B' THEN 1 ELSE 0 END) as grade_b,
                SUM(CASE WHEN result='BS' THEN 1 ELSE 0 END) as grade_bs,
                SUM(CASE WHEN status='REJECTED' THEN 1 ELSE 0 END) as rejected")
            ->whereDate('created_at', '>=', now()->subDays($days - 1))
            ->when(!empty($f['machine_id']), fn($q) => $q->whereHas('roll', fn($r) => $r->where('machine_id', $f['machine_id'])))
            ->when(!empty($f['operator_id']), fn($q) => $q->where('operator_id', $f['operator_id']))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => [
                'date'     => $r->date,
                'total'    => (int) $r->total,
                'grade_a'  => (int) $r->grade_a,
                'grade_b'  => (int) $r->grade_b,
                'grade_bs' => (int) $r->grade_bs,
                'rejected' => (int) $r->rejected,
            ])
            ->values()
            ->toArray();
    }

    // ── Defect Frequency ──────────────────────────────────────────────────

    public function defects(array $f = []): array
    {
        return Defect::query()
            ->join('defect_types', 'defects.defect_type_id', '=', 'defect_types.id')
            ->selectRaw('defect_types.defect_name,
                COUNT(*) as count,
                SUM(defects.point) as total_points,
                AVG(defects.position_meter) as avg_position')
            ->when(!empty($f['date_from']), fn($q) => $q->whereHas('inspection', fn($qi) => $qi->whereDate('created_at', '>=', $f['date_from'])))
            ->when(!empty($f['date_to']), fn($q) => $q->whereHas('inspection', fn($qi) => $qi->whereDate('created_at', '<=', $f['date_to'])))
            ->when(!empty($f['machine_id']), fn($q) => $q->whereHas('inspection.roll', fn($r) => $r->where('machine_id', $f['machine_id'])))
            ->groupBy('defect_types.id', 'defect_types.defect_name')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->map(fn($r) => [
                'defect_type'  => $r->defect_name,
                'count'        => (int) $r->count,
                'total_points' => (int) $r->total_points,
                'avg_position' => round((float) $r->avg_position, 2),
            ])
            ->toArray();
    }

    // ── Machine Performance ───────────────────────────────────────────────

    public function machines(array $f = []): array
    {
        return Inspection::query()
            ->join('fabric_rolls', 'inspections.roll_id', '=', 'fabric_rolls.id')
            ->join('machines', 'fabric_rolls.machine_id', '=', 'machines.id')
            ->selectRaw("machines.machine_name,
                COUNT(*) as total,
                SUM(CASE WHEN inspections.result='A' THEN 1 ELSE 0 END) as grade_a,
                SUM(CASE WHEN inspections.result='B' THEN 1 ELSE 0 END) as grade_b,
                SUM(CASE WHEN inspections.result='BS' THEN 1 ELSE 0 END) as grade_bs,
                AVG(inspections.score) as avg_score,
                SUM(CASE WHEN inspections.status='REJECTED' THEN 1 ELSE 0 END) as rejection_count")
            ->when(!empty($f['date_from']), fn($q) => $q->whereDate('inspections.created_at', '>=', $f['date_from']))
            ->when(!empty($f['date_to']), fn($q) => $q->whereDate('inspections.created_at', '<=', $f['date_to']))
            ->whereNotNull('fabric_rolls.machine_id')
            ->groupBy('machines.id', 'machines.machine_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'machine'         => $r->machine_name,
                'total'           => (int) $r->total,
                'grade_a_rate'    => $r->total > 0 ? round($r->grade_a / $r->total * 100, 1) : 0,
                'grade_b_rate'    => $r->total > 0 ? round($r->grade_b / $r->total * 100, 1) : 0,
                'grade_bs_rate'   => $r->total > 0 ? round($r->grade_bs / $r->total * 100, 1) : 0,
                'avg_score'       => round((float) $r->avg_score, 2),
                'rejection_count' => (int) $r->rejection_count,
            ])
            ->toArray();
    }

    // ── Paginated Inspection Table ────────────────────────────────────────

    public function inspections(array $f = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseInspectionQuery($f)
            ->with(['roll.machine', 'roll.inspectionRequest.client', 'operator', 'validator'])
            ->orderByDesc('inspections.created_at')
            ->paginate($perPage);
    }

    // ── CSV Export Rows ───────────────────────────────────────────────────

    public function exportRows(array $f = []): \Illuminate\Support\Collection
    {
        return $this->baseInspectionQuery($f)
            ->with(['roll.machine', 'roll.inspectionRequest.client', 'operator', 'validator'])
            ->orderByDesc('inspections.created_at')
            ->limit(5000)
            ->get();
    }
}
