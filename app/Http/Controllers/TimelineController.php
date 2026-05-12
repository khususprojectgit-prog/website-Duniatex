<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Inspection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TimelineController
 *
 * Exposes the audit trail timeline for a single inspection.
 * Business logic lives entirely in AuditLog::timelineForInspection().
 *
 * Route: GET /api/inspections/{inspection}/timeline
 * Auth:  Any authenticated role (operators restricted to own inspections).
 */
class TimelineController extends Controller
{
    public function show(Request $request, Inspection $inspection): JsonResponse
    {
        $user = $request->user();

        // Operators may only view timelines for their own inspections.
        if ($user->role === 'operator' && $inspection->operator_id !== $user->id) {
            return $this->error('Forbidden.', null, 403);
        }

        $timeline = AuditLog::timelineForInspection($inspection->id);

        $data = $timeline->map(fn (AuditLog $log) => [
            'id'           => $log->id,
            'action'       => $log->action,
            'action_label' => $log->action_label,
            'action_icon'  => $log->action_icon,
            'description'  => $log->description,
            'metadata'     => $log->metadata,
            'created_at'   => $log->created_at?->toIso8601String(),
            'user'         => $log->user ? [
                'id'   => $log->user->id,
                'name' => $log->user->name,
                'role' => $log->user->role ?? null,
            ] : null,
        ]);

        return $this->success('Inspection timeline retrieved.', $data);
    }
}
