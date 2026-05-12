<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FabricRoll;
use App\Models\InspectionRequest;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InspectionRequestController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    /** GET /api/admin/inspection-requests */
    public function index(Request $request): JsonResponse
    {
        $requests = InspectionRequest::with('client', 'qc')
            ->withCount('fabricRolls')
            ->when($request->status,    fn ($q) => $q->where('status', $request->status))
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->when($request->search,    fn ($q) => $q->where('request_code', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate(25);

        return $this->successPaginated('Inspection requests retrieved.', $requests);
    }

    /**
     * POST /api/admin/inspection-requests
     *
     * Creates an InspectionRequest and auto-generates {total_roll} FabricRolls.
     * All rolls start in NEW status, machine_id is shared across all rolls.
     * roll_code is auto-generated: ROLL-YYYYMMDD-XXXX (sequential).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id'    => ['required', 'exists:clients,id'],
            'qc_id'        => ['nullable', 'exists:users,id'],
            'machine_id'   => ['nullable', 'exists:machines,id'],
            'total_roll'   => ['required', 'integer', 'min:1', 'max:200'],
            'length_meter' => ['required', 'numeric', 'min:0.1', 'max:9999'],
            'batch_number' => ['nullable', 'string', 'max:50'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $result = DB::transaction(function () use ($data, $request) {

            // 1. Create inspection request with inline auto-generated code
            // Note: InspectionRequest has no SoftDeletes — use count() not withTrashed()
            $reqCount    = InspectionRequest::count() + 1;
            $requestCode = 'REQ-' . now()->format('Ymd') . '-' . str_pad($reqCount, 4, '0', STR_PAD_LEFT);

            $ir = InspectionRequest::create([
                'client_id'    => $data['client_id'],
                'qc_id'        => $data['qc_id'] ?? null,
                'request_code' => $requestCode,
                'status'       => 'NEW',
                'request_date' => now(),
                'notes'        => $data['notes'] ?? null,
            ]);

            // 2. Auto-generate fabric rolls — FabricRoll has no SoftDeletes, use count()
            $base = FabricRoll::count();
            for ($i = 1; $i <= $data['total_roll']; $i++) {
                $rollCode = 'ROLL-' . now()->format('Ymd') . '-' . str_pad($base + $i, 4, '0', STR_PAD_LEFT);
                FabricRoll::create([
                    'request_id'   => $ir->id,
                    'machine_id'   => $data['machine_id'] ?? null,
                    'roll_code'    => $rollCode,
                    'status'       => 'NEW',
                    'length_meter' => $data['length_meter'],
                    'batch_number' => $data['batch_number'] ?? null,
                ]);
            }

            // 3. Audit
            $this->audit->log(
                'request_created',
                "Admin membuat inspection request {$ir->request_code} dengan {$data['total_roll']} roll",
                [
                    'user_id'    => $request->user()->id,
                    'request_id' => $ir->id,
                    'metadata'   => [
                        'request_code' => $ir->request_code,
                        'client_id'    => $ir->client_id,
                        'total_roll'   => $data['total_roll'],
                        'length_meter' => $data['length_meter'],
                    ],
                ]
            );

            return $ir->load('client', 'qc', 'fabricRolls');
        });

        return $this->success(
            "Inspection request created with {$data['total_roll']} rolls.",
            $result,
            201
        );
    }
}
