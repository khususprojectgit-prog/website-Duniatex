<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    /** GET /api/admin/machines */
    public function index(Request $request): JsonResponse
    {
        $machines = Machine::query()
            ->when($request->machine_type, fn ($q) => $q->where('machine_type', $request->machine_type))
            ->when($request->search, fn ($q) => $q->where('machine_name', 'like', "%{$request->search}%"))
            ->withCount('machineIssues')
            ->orderBy('machine_name')
            ->paginate(20);

        return $this->successPaginated('Machines retrieved.', $machines);
    }

    /** POST /api/admin/machines */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_name' => ['required', 'string', 'max:100', 'unique:machines,machine_name'],
            'machine_type' => ['required', 'string', 'max:50'],
            'location'     => ['nullable', 'string', 'max:100'],
        ]);

        $machine = Machine::create($data);

        return $this->success('Machine created.', $machine, 201);
    }

    /** GET /api/admin/machines/{machine} */
    public function show(Machine $machine): JsonResponse
    {
        return $this->success('Machine retrieved.', $machine->load('machineIssues.reporter'));
    }

    /** PUT /api/admin/machines/{machine} */
    public function update(Request $request, Machine $machine): JsonResponse
    {
        $data = $request->validate([
            'machine_name' => ['sometimes', 'string', 'max:100', "unique:machines,machine_name,{$machine->id}"],
            'machine_type' => ['sometimes', 'string', 'max:50'],
            'location'     => ['nullable', 'string', 'max:100'],
        ]);

        $machine->update($data);

        return $this->success('Machine updated.', $machine);
    }

    /** DELETE /api/admin/machines/{machine} */
    public function destroy(Machine $machine): JsonResponse
    {
        if ($machine->fabricRolls()->exists()) {
            return $this->error('Cannot delete machine with existing fabric rolls.', null, 422);
        }

        $machine->delete();

        return $this->success('Machine deleted.');
    }
}
