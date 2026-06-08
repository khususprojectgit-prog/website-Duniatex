<?php

namespace App\Http\Controllers\QC;

use App\Enums\InspectionRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\InspectionRequest;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InspectionRequestController extends Controller
{
    public function __construct(protected AuditService $audit) {}
    /** GET /api/qc/requests */
    public function index(Request $request): JsonResponse
    {
        $requests = InspectionRequest::with('client', 'qc')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successPaginated('Inspection requests retrieved.', $requests);
    }

    /** POST /api/qc/requests */
    public function store(Request $request): JsonResponse
    {
       $data = $request->validate([
    'client_id'    => ['required', 'exists:clients,id'],
    'request_code' => ['required', 'string', 'max:50', 'unique:inspection_requests,request_code'],
    'opk'          => ['required', 'string', 'max:50'],
    'notes'        => ['nullable', 'string'],
]);

$data['qc_id']        = $request->user()->id;
$data['status']       = 'NEW';
$data['request_date'] = now(); // ← INI WAJIB

$ir = InspectionRequest::create($data);

        $this->audit->log(
            'request_created',
            "QC membuat inspection request {$ir->request_code} untuk klien",
            [
                'user_id'    => $request->user()->id,
                'request_id' => $ir->id,
                'metadata'   => [
                    'request_code' => $ir->request_code,
                    'client_id'    => $ir->client_id,
                ],
            ]
        );

        return $this->success('Inspection request created.', $ir->load('client'), 201);
    }

    /** GET /api/qc/requests/{inspectionRequest} */
    public function show(InspectionRequest $inspectionRequest): JsonResponse
    {
        return $this->success('Inspection request retrieved.',
            $inspectionRequest->load('client', 'qc', 'fabricRolls.latestInspection.defects', 'fabricRolls.machine')
        );
    }

    /** PUT /api/qc/requests/{inspectionRequest} */
    public function update(Request $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        $data = $request->validate([
            'client_id'    => ['sometimes', 'exists:clients,id'],
            'request_code' => ['sometimes', 'string', 'max:50', "unique:inspection_requests,request_code,{$inspectionRequest->id}"],
            'opk'          => ['sometimes', 'string', 'max:50'],
            'status'       => ['sometimes', Rule::in(['NEW', 'IN_PROGRESS', 'COMPLETED'])],
            'notes'        => ['nullable', 'string'],
        ]);

        $inspectionRequest->update($data);

        return $this->success('Inspection request updated.', $inspectionRequest);
    }

    /** DELETE /api/qc/requests/{inspectionRequest} */
    public function destroy(InspectionRequest $inspectionRequest): JsonResponse
    {
        // Only NEW requests can be deleted — any active/completed request is protected.
        if ($inspectionRequest->status !== InspectionRequestStatus::NEW) {
            return $this->error('Only NEW requests can be deleted.', null, 422);
        }

        $inspectionRequest->delete();

        return $this->success('Inspection request deleted.');
    }
}
