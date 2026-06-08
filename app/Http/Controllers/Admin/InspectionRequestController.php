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
        $requests = InspectionRequest::with('client', 'qc', 'yarnType', 'setting')
            ->withCount('fabricRolls')
            ->when($request->status,    fn ($q) => $q->where('status', $request->status))
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->when($request->search, function ($q) use ($request) {
                $q->where('request_code', 'like', "%{$request->search}%")
                  ->orWhere('opk', 'like', "%{$request->search}%")
                  ->orWhereHas('client', function ($cq) use ($request) {
                      $cq->where('client_name', 'like', "%{$request->search}%");
                  });
            })
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
            'yarn_type_id' => ['nullable', 'exists:yarn_types,id'],
            'setting_id'   => ['nullable', 'exists:settings,id'],
            'opk'          => ['required', 'string', 'max:50'],
            'qc_id'        => ['nullable', 'exists:users,id'],
            'machine_id'   => ['nullable', 'exists:machines,id'],
            'operator_id'  => ['nullable', 'exists:users,id'],
            'total_roll'   => ['required', 'integer', 'min:1', 'max:10000'],
            'batch_number' => ['nullable', 'string', 'max:50'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'gramasi'      => ['nullable', 'string', 'max:50'],
        ]);

        $operatorId = $data['operator_id'] ?? null;
        if (! $operatorId) {
            $firstOperator = \App\Models\User::where('role', 'qc')
                ->where('status', 'active')
                ->first();
            if ($firstOperator) {
                $operatorId = $firstOperator->id;
            } else {
                return $this->error('Tidak ada QC aktif di sistem.', null, 422);
            }
        } else {
            $operator = \App\Models\User::where('id', $operatorId)
                ->where('role', 'qc')
                ->where('status', 'active')
                ->first();

            if (! $operator) {
                return $this->error('QC tidak ditemukan atau tidak aktif.', null, 422);
            }
        }

        $result = DB::transaction(function () use ($data, $operatorId, $request) {

            // 1. Create inspection request with inline auto-generated code
            // Note: InspectionRequest has no SoftDeletes — use count() not withTrashed()
            $reqCount    = InspectionRequest::count() + 1;
            $requestCode = 'REQ-' . now()->format('Ymd') . '-' . str_pad($reqCount, 4, '0', STR_PAD_LEFT);

            $ir = InspectionRequest::create([
                'client_id'    => $data['client_id'],
                'yarn_type_id' => $data['yarn_type_id'] ?? null,
                'setting_id'   => $data['setting_id'] ?? null,
                'gramasi'      => $data['gramasi'] ?? null,
                'qc_id'        => $data['qc_id'] ?? null,
                'request_code' => $requestCode,
                'opk'          => $data['opk'],
                'status'       => 'NEW',
                'request_date' => now(),
                'notes'        => $data['notes'] ?? null,
            ]);

            // 2. Auto-generate fabric rolls — Bulk Insert
            $base = FabricRoll::count();
            $rollsToInsert = [];
            for ($i = 1; $i <= $data['total_roll']; $i++) {
                $rollCode = 'ROLL-' . now()->format('Ymd') . '-' . str_pad($base + $i, 4, '0', STR_PAD_LEFT);
                $rollsToInsert[] = [
                    'request_id'   => $ir->id,
                    'machine_id'   => $data['machine_id'] ?? null,
                    'operator_id'  => $operatorId,
                    'roll_code'    => $rollCode,
                    'status'       => 'NEW',
                    'batch_number' => $data['batch_number'] ?? null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }
            foreach (array_chunk($rollsToInsert, 500) as $chunk) {
                FabricRoll::insert($chunk);
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
                        'operator_id'  => $operatorId,
                    ],
                ]
            );

            return $ir->load('client', 'qc', 'fabricRolls', 'yarnType', 'setting');
        });

        return $this->success(
            "Inspection request created with {$data['total_roll']} rolls.",
            $result,
            201
        );
    }

    /** PUT /api/admin/inspection-requests/{inspectionRequest} */
    public function update(Request $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        $data = $request->validate([
            'client_id'    => ['sometimes', 'required', 'exists:clients,id'],
            'yarn_type_id' => ['nullable', 'exists:yarn_types,id'],
            'setting_id'   => ['nullable', 'exists:settings,id'],
            'gramasi'      => ['nullable', 'string', 'max:50'],
            'opk'          => ['sometimes', 'required', 'string', 'max:50'],
            'qc_id'        => ['nullable', 'exists:users,id'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $inspectionRequest->update($data);

        $this->audit->log(
            'request_updated',
            "Admin memperbarui inspection request {$inspectionRequest->request_code}",
            [
                'user_id'    => $request->user()->id,
                'request_id' => $inspectionRequest->id,
                'metadata'   => $data,
            ]
        );

        return $this->success('Inspection request updated successfully.', $inspectionRequest->load('client', 'yarnType', 'setting', 'qc'));
    }
}
