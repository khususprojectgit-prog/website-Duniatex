<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        // On POST: all fields required. On PUT/PATCH: all fields optional.
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $clientId = $this->route('client')?->id;

        return [
            'client_name'    => [$required, 'string', 'max:100', "unique:clients,client_name,{$clientId}"],
            'company'        => [$required, 'string', 'max:150'],
            'contact_person' => [$required, 'string', 'max:100'],
            'phone'          => [$required, 'string', 'max:20'],
            'address'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
