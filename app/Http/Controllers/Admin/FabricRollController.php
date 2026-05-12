<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FabricRoll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FabricRollController extends Controller
{
    /** GET /api/admin/fabric-rolls */
    public function index(Request $request): JsonResponse
    {
        $rolls = FabricRoll::with(
            'machine',
            'inspectionRequest.client',
            'inspection.operator'
        )
            ->when($request->status,     fn ($q) => $q->where('status', $request->status))
            ->when($request->request_id, fn ($q) => $q->where('request_id', $request->request_id))
            ->when($request->search,     fn ($q) => $q->where('roll_code', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->successPaginated('Fabric rolls retrieved.', $rolls);
    }
}
