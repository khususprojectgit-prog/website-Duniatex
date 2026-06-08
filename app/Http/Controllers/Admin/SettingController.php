<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    /** GET /api/admin/settings */
    public function index(): JsonResponse
    {
        return $this->success('Settings retrieved.', Setting::orderBy('setting_name')->get());
    }

    /** POST /api/admin/settings */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'setting_name' => ['required', 'string', 'max:150', 'unique:settings,setting_name'],
            'description'  => ['nullable', 'string'],
        ]);

        $setting = Setting::create($data);

        $this->audit->log(
            'setting_created',
            "Admin mendaftarkan setting baru: {$setting->setting_name}",
            [
                'user_id'  => $request->user()->id,
                'metadata' => [
                    'setting_id'   => $setting->id,
                    'setting_name' => $setting->setting_name,
                ],
            ]
        );

        return $this->success('Setting created.', $setting, 201);
    }

    /** GET /api/admin/settings/{setting} */
    public function show(Setting $setting): JsonResponse
    {
        return $this->success('Setting retrieved.', $setting);
    }

    /** PUT /api/admin/settings/{setting} */
    public function update(Request $request, Setting $setting): JsonResponse
    {
        $data = $request->validate([
            'setting_name' => ['sometimes', 'string', 'max:150', "unique:settings,setting_name,{$setting->id}"],
            'description'  => ['nullable', 'string'],
        ]);

        $setting->update($data);

        return $this->success('Setting updated.', $setting);
    }

    /** DELETE /api/admin/settings/{setting} */
    public function destroy(Setting $setting): JsonResponse
    {
        $setting->delete();
        return $this->success('Setting deleted.');
    }
}
