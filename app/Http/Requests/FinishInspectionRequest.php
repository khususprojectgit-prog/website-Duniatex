<?php

namespace App\Http\Requests;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FinishInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->role !== 'qc') {
            return false;
        }

        /** @var Inspection|null $inspection */
        $inspection = $this->route('inspection');

        if (! $inspection || $inspection->operator_id !== $this->user()->id) {
            return false;
        }

        return $inspection->status === InspectionStatus::IN_PROGRESS;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('gramasi')) {
            $this->merge([
                'gramasi' => str_replace(' ', '', (string) $this->input('gramasi')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'length_meter'       => ['required', 'numeric', 'min:0.1', 'max:9999'],
            'gramasi'            => ['required', 'string', 'max:20', 'regex:/^\d+(\.\d{1,2})?-\d+(\.\d{1,2})?$/'],
            'lebar'              => ['required', 'numeric', 'min:1', 'max:9999'],
            'weight_kg'          => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'machine_name'       => ['required', 'string', 'max:100'],
            'batch_number'       => ['required', 'string', 'max:100'],
            'manual_roll_number' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'result'             => ['required', \Illuminate\Validation\Rule::in(['A', 'B', 'BS'])],
            'potongan_1_kg'      => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'potongan_2_kg'      => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'keterangan_visual'  => ['nullable', 'string', 'max:2000'],
            'catatan'            => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $gramasi = $this->input('gramasi');
            if (! is_string($gramasi) || ! preg_match('/^(\d+(?:\.\d{1,2})?)-(\d+(?:\.\d{1,2})?)$/', $gramasi, $m)) {
                return;
            }

            $min = (float) $m[1];
            $max = (float) $m[2];

            if ($min < 1 || $max > 9999) {
                $v->errors()->add('gramasi', 'Nilai gramasi harus antara 1 dan 9999 g/m².');
            } elseif ($min > $max) {
                $v->errors()->add('gramasi', 'Nilai minimum gramasi tidak boleh lebih besar dari maksimum.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'length_meter.required' => 'Panjang kain (meter) wajib diisi.',
            'length_meter.min'      => 'Panjang kain minimal 0,1 meter.',
            'gramasi.required'      => 'Gramasi wajib diisi.',
            'gramasi.regex'         => 'Format gramasi: min-maks (contoh: 145-150).',
            'lebar.required'        => 'Lebar wajib diisi.',
            'lebar.min'             => 'Lebar minimal 1.',
            'weight_kg.required'    => 'Berat (KG) wajib diisi.',
            'weight_kg.min'         => 'Berat minimal 0.01.',
            'machine_name.required' => 'Nama mesin wajib diisi.',
            'batch_number.required' => 'Lot wajib diisi.',
            'result.required'       => 'Grade hasil inspeksi wajib diisi.',
            'result.in'             => 'Grade hasil inspeksi tidak valid.',
        ];
    }

    public function failedAuthorization(): \Illuminate\Http\Exceptions\HttpResponseException
    {
        $inspection = $this->route('inspection');

        $message = match (true) {
            $this->user()?->role !== 'qc'
                => 'Forbidden. Only QC can finish inspections.',
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
