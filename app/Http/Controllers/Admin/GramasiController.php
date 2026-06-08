<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gramasi;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GramasiController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    /** GET /api/admin/gramasis */
    public function index(): JsonResponse
    {
        return $this->success('Gramasis retrieved.', Gramasi::orderBy('range')->get());
    }

    /** POST /api/admin/gramasis */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'range'       => ['required', 'string', 'max:50', 'unique:gramasis,range', 'regex:/^\d+(\.\d{1,2})?-\d+(\.\d{1,2})?$/'],
            'description' => ['nullable', 'string'],
        ], [
            'range.regex' => 'Format gramasi harus berupa range min-maks (contoh: 140-145).',
        ]);

        // Clean spaces if any
        $data['range'] = str_replace(' ', '', $data['range']);

        $gramasi = Gramasi::create($data);

        $this->audit->log(
            'gramasi_created',
            "Admin mendaftarkan range gramasi baru: {$gramasi->range}",
            [
                'user_id'  => $request->user()->id,
                'metadata' => [
                    'gramasi_id' => $gramasi->id,
                    'range'      => $gramasi->range,
                ],
            ]
        );

        return $this->success('Gramasi created.', $gramasi, 201);
    }

    /** GET /api/admin/gramasis/{gramasi} */
    public function show(Gramasi $gramasi): JsonResponse
    {
        return $this->success('Gramasi retrieved.', $gramasi);
    }

    /** PUT /api/admin/gramasis/{gramasi} */
    public function update(Request $request, Gramasi $gramasi): JsonResponse
    {
        $data = $request->validate([
            'range'       => ['sometimes', 'string', 'max:50', "unique:gramasis,range,{$gramasi->id}", 'regex:/^\d+(\.\d{1,2})?-\d+(\.\d{1,2})?$/'],
            'description' => ['nullable', 'string'],
        ], [
            'range.regex' => 'Format gramasi harus berupa range min-maks (contoh: 140-145).',
        ]);

        if (isset($data['range'])) {
            $data['range'] = str_replace(' ', '', $data['range']);
        }

        $gramasi->update($data);

        return $this->success('Gramasi updated.', $gramasi);
    }

    /** DELETE /api/admin/gramasis/{gramasi} */
    public function destroy(Gramasi $gramasi): JsonResponse
    {
        $gramasi->delete();
        return $this->success('Gramasi deleted.');
    }
}
