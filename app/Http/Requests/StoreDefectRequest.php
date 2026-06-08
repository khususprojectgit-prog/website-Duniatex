<?php

namespace App\Http\Requests;

use App\Models\DefectType;
use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDefectRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->role !== 'qc') {
            return false;
        }

        /** @var Inspection|null $inspection */
        $inspection = $this->route('inspection');

        return $inspection && $inspection->operator_id === $this->user()->id;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('position_meter') && is_string($this->input('position_meter'))) {
            $this->merge([
                'position_meter' => str_replace(' ', '', $this->input('position_meter')),
            ]);
        }
    }

    public function rules(): array
    {
        /** @var Inspection|null $inspection */
        $inspection = $this->route('inspection');
        $maxLength  = $inspection?->length_meter ? (float) $inspection->length_meter : 99999.99;

        $defectType = DefectType::find($this->input('defect_type_id'));
        $isRange    = DefectType::usesRangePosition($defectType?->defect_name);

        if ($isRange) {
            return [
                'defect_type_id' => ['required', 'integer', 'exists:defect_types,id'],
                'position_meter' => [
                    'required',
                    'string',
                    'max:30',
                    'regex:/^\d+(\.\d{1,2})?-\d+(\.\d{1,2})?$/',
                ],
                'point'          => ['required', 'integer', 'min:1', 'max:4'],
                'notes'          => ['nullable', 'string', 'max:255'],
                'side'           => ['required', \Illuminate\Validation\Rule::in(['depan', 'belakang'])],
            ];
        }

        return [
            'defect_type_id' => ['required', 'integer', 'exists:defect_types,id'],
            'position_meter' => ['required', 'numeric', 'min:0.1', 'max:'.$maxLength],
            'point'          => ['required', 'integer', 'min:1', 'max:4'],
            'notes'          => ['nullable', 'string', 'max:255'],
            'side'           => ['required', \Illuminate\Validation\Rule::in(['depan', 'belakang'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $defectType = DefectType::find($this->input('defect_type_id'));
            if (! DefectType::usesRangePosition($defectType?->defect_name)) {
                return;
            }

            $pos = $this->input('position_meter');
            if (! is_string($pos) || ! preg_match('/^(\d+(?:\.\d{1,2})?)-(\d+(?:\.\d{1,2})?)$/', $pos, $m)) {
                return;
            }

            $from = (float) $m[1];
            $to   = (float) $m[2];

            /** @var Inspection|null $inspection */
            $inspection = $this->route('inspection');
            $maxLength  = $inspection?->length_meter ? (float) $inspection->length_meter : 99999.99;

            if ($from < 0.1 || $to < 0.1) {
                $v->errors()->add('position_meter', 'Posisi harus lebih dari 0 meter.');
            } elseif ($from > $to) {
                $v->errors()->add('position_meter', 'Meter awal tidak boleh lebih besar dari meter akhir.');
            } elseif ($from > $maxLength || $to > $maxLength) {
                $v->errors()->add('position_meter', 'Posisi cacat melebihi panjang kain yang diinput.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'defect_type_id.exists'   => 'The selected defect type does not exist.',
            'position_meter.min'      => 'Position cannot be negative.',
            'position_meter.max'      => 'Posisi cacat melebihi panjang kain yang diinput.',
            'position_meter.regex'    => 'Format posisi: dari-sampai meter (contoh: 10-25).',
            'point.min'               => 'Defect point must be between 1 and 4 (4-Point System).',
            'point.max'               => 'Defect point must be between 1 and 4 (4-Point System).',
        ];
    }

    public function failedAuthorization(): \Illuminate\Http\Exceptions\HttpResponseException
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json(['message' => 'Forbidden. You can only record defects on your own active inspection.'], 403)
        );
    }
}
