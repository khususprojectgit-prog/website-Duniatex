<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFabricRollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['qc', 'admin'], true);
    }

    public function rules(): array
    {
        // On POST: all fields required. On PUT/PATCH: all fields optional.
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $rollId   = $this->route('fabricRoll')?->id;

        return [
            'roll_code'    => [$required, 'string', 'max:50', "unique:fabric_rolls,roll_code,{$rollId}"],
            'operator_id'  => [$required, 'integer', 'exists:users,id'],
            'machine_id'   => [$required, 'integer', 'exists:machines,id'],
            'batch_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'operator_id.exists' => 'Operator tidak ditemukan.',
            'machine_id.exists'  => 'The selected machine does not exist.',
        ];
    }
}
