<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PotonganController extends Controller
{
    /**
     * GET /api/qc/potongan
     * Returns inspections with potongan (cuts) details.
     */
    public function index(Request $request): JsonResponse
    {
        $potongans = Inspection::with('roll.machine', 'roll.inspectionRequest.client', 'operator')
            ->where(function ($q) {
                $q->where('potongan_1_kg', '>', 0)
                  ->orWhere('potongan_2_kg', '>', 0);
            })
            ->orderByDesc('created_at')
            ->paginate(25);

        return $this->successPaginated('Potongan data retrieved.', $potongans);
    }
}
