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
            'target_column' => ['required', 'string', 'regex:/^[a-z]+[a-z0-9_]*[a-z0-9]?$/'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'filter' => ['nullable', 'array'],
            'filter.colname' => ['required_with:filter', 'string'],
            'filter.value' => ['required_with:filter', 'integer'],
        ];
    }
}
