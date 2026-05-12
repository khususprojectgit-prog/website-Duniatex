<?php

namespace App\Http\Requests;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

class StoreDefectRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->role !== 'operator') {
            return false;
        }

        // Ownership check: operator can only add defects to their own inspection
        /** @var Inspection|null $inspection */
        $inspection = $this->route('inspection');

        return $inspection && $inspection->operator_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'defect_type_id' => ['required', 'integer', 'exists:defect_types,id'],
            'position_meter' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'point'          => ['required', 'integer', 'min:1', 'max:4'],
            'notes'          => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'defect_type_id.exists' => 'The selected defect type does not exist.',
            'position_meter.min'    => 'Position cannot be negative.',
            'position_meter.max'    => 'Position exceeds maximum fabric length.',
            'point.min'             => 'Defect point must be between 1 and 4 (4-Point System).',
            'point.max'             => 'Defect point must be between 1 and 4 (4-Point System).',
        ];
    }

    public function failedAuthorization(): \Illuminate\Http\Exceptions\HttpResponseException
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json(['message' => 'Forbidden. You can only record defects on your own active inspection.'], 403)
        );
    }
}
