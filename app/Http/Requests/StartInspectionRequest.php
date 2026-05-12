<?php

namespace App\Http\Requests;

use App\Enums\FabricRollStatus;
use App\Models\FabricRoll;
use Illuminate\Foundation\Http\FormRequest;

class StartInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only operators may start inspections
        if ($this->user()?->role !== 'operator') {
            return false;
        }

        /** @var FabricRoll|null $roll */
        $roll = $this->route('fabricRoll');

        // Roll must be NEW (first inspection) or PENDING (re-inspection after QC rejection)
        if ($roll && ! FabricRollStatus::tryFrom($roll->status instanceof FabricRollStatus ? $roll->status->value : $roll->status)?->isAvailable()) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        // No body fields required — authorization carries full validation
        return [];
    }

    public function failedAuthorization(): \Illuminate\Http\Exceptions\HttpResponseException
    {
        /** @var FabricRoll|null $roll */
        $roll = $this->route('fabricRoll');

        $statusValue = $roll?->status instanceof FabricRollStatus
            ? $roll->status->value
            : $roll?->status;

        $message = ($roll && ! FabricRollStatus::tryFrom($statusValue)?->isAvailable())
            ? "Fabric roll [{$roll->roll_code}] is not available for inspection (status: {$statusValue}). Only NEW or PENDING rolls can be started."
            : 'Forbidden. Only operators can start inspections.';

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json(['message' => $message], 403)
        );
    }
}
