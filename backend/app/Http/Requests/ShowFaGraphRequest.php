<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowFaGraphRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_column' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'sample_nos' => ['nullable', 'array'],
            'sample_nos.*' => ['integer'],
        ];
    }
}
