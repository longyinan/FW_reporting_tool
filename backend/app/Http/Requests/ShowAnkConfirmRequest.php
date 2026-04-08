<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowAnkConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sampleNos' => ['nullable', 'string'],
            'sort' => 'nullable|integer|in:1,2',
            'condition' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
