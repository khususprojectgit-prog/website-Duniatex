<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FabricRollStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReassignFabricRollRequest;
use App\Models\FabricRoll;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FabricRollController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    /** GET /api/admin/fabric-rolls */
    public function index(Request $request): JsonResponse
    {
        $rolls = FabricRoll::with(
            'machine',
            'assignedOperator',
            'inspectionRequest.client',
            'latestInspection.operator',
            'displayInspection.operator',
        )
            ->when($request->status, function ($q) use ($request) {
                if ($request->status === 'SUBMITTED') {
                    $q->whereIn('status', ['SUBMITTED', 'VALIDATED']);
                } else {
                    $q->where('status', $request->status);
                }
            })
            ->when($request->request_id, fn ($q) => $q->where('request_id', $request->request_id))
            ->when($request->opk, function ($q) use ($request) {
                $q->whereHas('inspectionRequest', function ($rq) use ($request) {
                    $rq->where('opk', 'like', "%{$request->opk}%");
                });
            })
            ->when($request->search, function ($q) use ($request) {
                $q->where('roll_code', 'like', "%{$request->search}%")
                  ->orWhereHas('inspectionRequest.client', function ($cq) use ($request) {
                      $cq->where('client_name', 'like', "%{$request->search}%");
                  });
            })
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->successPaginated('Fabric rolls retrieved.', $rolls);
    }

    /**
     * PATCH /api/admin/fabric-rolls/{fabricRoll}/reassign
     * Reassign operator for rolls in NEW or PENDING status only.
     */
    public function reassign(ReassignFabricRollRequest $request, FabricRoll $fabricRoll): JsonResponse
    {
        $status = $fabricRoll->status instanceof FabricRollStatus
            ? $fabricRoll->status
            : FabricRollStatus::tryFrom($fabricRoll->status);

        if (! in_array($status, [FabricRollStatus::NEW, FabricRollStatus::PENDING], true)) {
            return $this->error('Hanya roll berstatus NEW atau PENDING yang dapat di-reassign.', null, 409);
        }

        $previousId = $fabricRoll->operator_id;
        $fabricRoll->update(['operator_id' => $request->validated()['operator_id']]);

        $operator = User::find($fabricRoll->operator_id);

        $this->audit->log(
            'roll_operator_reassigned',
            "Admin menugaskan ulang roll {$fabricRoll->roll_code} ke operator {$operator?->name}",
            [
                'user_id'    => $request->user()->id,
                'roll_id'    => $fabricRoll->id,
                'request_id' => $fabricRoll->request_id,
                'metadata'   => [
                    'from_operator_id' => $previousId,
                    'to_operator_id'   => $fabricRoll->operator_id,
                ],
            ]
        );

        return $this->success(
            'Operator roll berhasil diubah.',
            $fabricRoll->fresh(['assignedOperator', 'machine', 'inspectionRequest.client'])
        );
    }
}
