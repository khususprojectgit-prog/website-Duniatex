<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignFabricRollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'operator_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'qc')->where('status', 'active')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'operator_id.exists' => 'QC tidak ditemukan atau tidak aktif.',
        ];
    }
}
