<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DefectType;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DefectTypeController extends Controller
{
    public function __construct(protected AuditService $audit) {}
    /** GET /api/admin/defect-types */
    public function index(): JsonResponse
    {
        return $this->success('Defect types retrieved.', DefectType::orderBy('defect_name')->get());
    }

    /** POST /api/admin/defect-types */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'defect_name'   => ['required', 'string', 'max:100', 'unique:defect_types,defect_name'],
            'category'      => ['nullable', 'string', 'max:50'],
            'default_point' => ['required', 'integer', 'min:1', 'max:4'],
            'description'   => ['nullable', 'string'],
        ]);

        $defectType = DefectType::create($data);

        $this->audit->log(
            'defect_type_created',
            "Admin mendaftarkan tipe cacat baru: {$defectType->defect_name} ({$defectType->default_point} poin)",
            [
                'user_id'  => $request->user()->id,
                'metadata' => [
                    'defect_type_id' => $defectType->id,
                    'defect_name'    => $defectType->defect_name,
                    'default_point'  => $defectType->default_point,
                ],
            ]
        );

        return $this->success('Defect type created.', $defectType, 201);
    }

    /** GET /api/admin/defect-types/{defectType} */
    public function show(DefectType $defectType): JsonResponse
    {
        return $this->success('Defect type retrieved.', $defectType->load('defects'));
    }

    /** PUT /api/admin/defect-types/{defectType} */
    public function update(Request $request, DefectType $defectType): JsonResponse
    {
        $data = $request->validate([
            'defect_name'   => ['sometimes', 'string', 'max:100', "unique:defect_types,defect_name,{$defectType->id}"],
            'category'      => ['nullable', 'string', 'max:50'],
            'default_point' => ['sometimes', 'integer', 'min:1', 'max:4'],
            'description'   => ['nullable', 'string'],
        ]);

        $defectType->update($data);

        return $this->success('Defect type updated.', $defectType);
    }

    /** DELETE /api/admin/defect-types/{defectType} */
    public function destroy(DefectType $defectType): JsonResponse
    {
        if ($defectType->defects()->exists()) {
            return $this->error('Cannot delete defect type that has been used in inspections.', null, 422);
        }

        $defectType->delete();

        return $this->success('Defect type deleted.');
    }
}
