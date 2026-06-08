<?php

namespace App\Http\Requests;

use App\Enums\FabricRollStatus;
use App\Enums\InspectionStatus;
use App\Models\FabricRoll;
use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->role !== 'qc') {
            return false;
        }

        if ($this->user()->status !== 'active') {
            return false;
        }

        /** @var FabricRoll|null $roll */
        $roll = $this->route('fabricRoll');

        if (! $roll) {
            return false;
        }

        $status = $roll->status instanceof FabricRollStatus
            ? $roll->status
            : FabricRollStatus::tryFrom($roll->status);

        if (! $status?->isAvailable()) {
            return false;
        }


        $hasActive = Inspection::where('operator_id', $this->user()->id)
            ->where('status', InspectionStatus::IN_PROGRESS->value)
            ->exists();

        return ! $hasActive;
    }

    public function rules(): array
    {
        return [
            'shift'        => ['required', Rule::in(['pagi', 'siang', 'malam'])],
            'weight_kg'    => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'yarn_name'    => ['nullable', 'string', 'max:100'],
            'qc_name'      => ['required', 'string', 'max:100'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'machine_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'shift.required'        => 'Pilih shift kerja (pagi, siang, atau malam).',
            'shift.in'              => 'Shift tidak valid.',
            'qc_name.required'      => 'Nama QC wajib diisi.',
        ];
    }

    public function failedAuthorization(): \Illuminate\Http\Exceptions\HttpResponseException
    {
        /** @var FabricRoll|null $roll */
        $roll = $this->route('fabricRoll');

        $message = 'Anda tidak dapat memulai inspeksi ini.';

        if ($this->user()?->role !== 'qc') {
            $message = 'Hanya QC yang dapat memulai inspeksi.';
        } elseif ($this->user()?->status !== 'active') {
            $message = 'Akun QC tidak aktif.';

        } elseif (Inspection::where('operator_id', $this->user()?->id)
            ->where('status', InspectionStatus::IN_PROGRESS->value)
            ->exists()) {
            $message = 'Selesaikan inspeksi yang sedang berjalan terlebih dahulu.';
        } elseif ($roll) {
            $statusValue = $roll->status instanceof FabricRollStatus
                ? $roll->status->value
                : $roll->status;

            $message = "Roll [{$roll->roll_code}] tidak tersedia (status: {$statusValue}).";
        }

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json(['message' => $message], 403)
        );
    }
}
