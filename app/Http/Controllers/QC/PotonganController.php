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
            ->whereIn('status', [
                \App\Enums\InspectionStatus::QC_VALIDATED->value,
                \App\Enums\InspectionStatus::VALIDATED->value,
                \App\Enums\InspectionStatus::RELEASED->value,
            ])
            ->where(function ($q) {
                $q->where('potongan_1_kg', '>', 0)
                  ->orWhere('potongan_2_kg', '>', 0);
            })
            ->orderByDesc('created_at')
            ->paginate(25);

        // Map data to include Netto calculation for easier frontend display
        $potongans->getCollection()->transform(function ($inspection) {
            $potongan1 = (float) $inspection->potongan_1_kg;
            $potongan2 = (float) $inspection->potongan_2_kg;
            $bruto = (float) $inspection->weight_kg;
            
            $inspection->total_potongan = $potongan1 + $potongan2;
            $inspection->weight_netto = $bruto - $inspection->total_potongan;
            
            return $inspection;
        });

        return $this->successPaginated('Potongan data retrieved.', $potongans);
    }
}
