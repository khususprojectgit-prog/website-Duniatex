<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\MachineIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MachineIssueController extends Controller
{
    /** GET /api/qc/machine-issues */
    public function index(Request $request): JsonResponse
    {
        $issues = MachineIssue::with('machine', 'reporter')
            ->when($request->machine_id, fn ($q) => $q->where('machine_id', $request->machine_id))
            ->when($request->status,     fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successPaginated('Machine issues retrieved.', $issues);
    }

    /** POST /api/qc/machine-issues */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id'  => ['required', 'exists:machines,id'],
            'issue_type'  => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
        ]);

        $data['reported_by'] = $request->user()->id;
        $data['status']      = 'OPEN';

        $issue = MachineIssue::create($data);

        return $this->success('Machine issue reported.', $issue->load('machine', 'reporter'), 201);
    }

    /** GET /api/qc/machine-issues/{machineIssue} */
    public function show(MachineIssue $machineIssue): JsonResponse
    {
        return $this->success('Machine issue retrieved.', $machineIssue->load('machine', 'reporter'));
    }

    /** PUT /api/qc/machine-issues/{machineIssue} */
    public function update(Request $request, MachineIssue $machineIssue): JsonResponse
    {
        $data = $request->validate([
            'issue_type'  => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'string'],
            'status'      => ['sometimes', Rule::in(['OPEN', 'IN_REPAIR', 'RESOLVED'])],
        ]);

        $machineIssue->update($data);

        return $this->success('Machine issue updated.', $machineIssue);
    }

    /** DELETE /api/qc/machine-issues/{machineIssue} */
    public function destroy(MachineIssue $machineIssue): JsonResponse
    {
        if ($machineIssue->status !== 'OPEN') {
            return $this->error('Only OPEN issues can be deleted.');
        }

        $machineIssue->delete();

        return $this->success('Machine issue deleted.');
    }
}
