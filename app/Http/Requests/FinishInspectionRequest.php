<?php

namespace App\Http\Requests;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

class FinishInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->role !== 'operator') {
            return false;
        }

        /** @var Inspection|null $inspection */
        $inspection = $this->route('inspection');

        // Ownership check
        if (! $inspection || $inspection->operator_id !== $this->user()->id) {
            return false;
        }

        // State check — must be IN_PROGRESS
        return $inspection->status === InspectionStatus::IN_PROGRESS;
    }

    public function rules(): array
    {
        return []; // No body fields required
    }

    public function failedAuthorization(): \Illuminate\Http\Exceptions\HttpResponseException
    {
        $inspection = $this->route('inspection');

        $message = match (true) {
            $this->user()?->role !== 'operator'
                => 'Forbidden. Only operators can finish inspections.',
            $inspection && $inspection->operator_id !== $this->user()?->id
                => 'Forbidden. You can only finish your own inspections.',
            $inspection && $inspection->status !== InspectionStatus::IN_PROGRESS
                => "Cannot finish inspection — current status is [{$inspection->status->value}].",
            default
                => 'Forbidden.',
        };

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json(['message' => $message], 403)
        );
    }
}
