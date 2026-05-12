<?php

namespace App\Http\Requests;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

class QCValidateInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['qc', 'admin'], true);
    }

    public function rules(): array
    {
        // 'reason' only required for rejection; validate() returns it when present
        return [
            'reason' => [
                $this->isRejection() ? 'required' : 'nullable',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A rejection reason of at least 10 characters is required.',
            'reason.min'      => 'The rejection reason must be at least 10 characters.',
        ];
    }

    /**
     * Detect whether this request is a rejection (vs. a validation).
     * The route name ends in 'reject' for rejection calls.
     */
    public function isRejection(): bool
    {
        return str_ends_with((string) $this->route()->getName(), 'reject')
            || str_ends_with($this->path(), '/reject');
    }
}
