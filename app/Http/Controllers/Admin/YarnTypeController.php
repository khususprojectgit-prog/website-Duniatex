<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\YarnType;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YarnTypeController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    /** GET /api/admin/yarn-types */
    public function index(): JsonResponse
    {
        return $this->success('Yarn types retrieved.', YarnType::orderBy('yarn_name')->get());
    }

    /** POST /api/admin/yarn-types */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'yarn_name' => ['required', 'string', 'max:100', 'unique:yarn_types,yarn_name'],
        ]);

        $yarnType = YarnType::create($data);

        $this->audit->log(
            'yarn_type_created',
            "Admin mendaftarkan jenis benang baru: {$yarnType->yarn_name}",
            [
                'user_id'  => $request->user()->id,
                'metadata' => [
                    'yarn_type_id' => $yarnType->id,
                    'yarn_name'    => $yarnType->yarn_name,
                ],
            ]
        );

        return $this->success('Yarn type created.', $yarnType, 201);
    }

    /** GET /api/admin/yarn-types/{yarnType} */
    public function show(YarnType $yarnType): JsonResponse
    {
        return $this->success('Yarn type retrieved.', $yarnType);
    }

    /** PUT /api/admin/yarn-types/{yarnType} */
    public function update(Request $request, YarnType $yarnType): JsonResponse
    {
        $data = $request->validate([
            'yarn_name' => ['sometimes', 'string', 'max:100', "unique:yarn_types,yarn_name,{$yarnType->id}"],
        ]);

        $yarnType->update($data);

        return $this->success('Yarn type updated.', $yarnType);
    }

    /** DELETE /api/admin/yarn-types/{yarnType} */
    public function destroy(YarnType $yarnType): JsonResponse
    {
        $yarnType->delete();
        return $this->success('Yarn type deleted.');
    }
}
