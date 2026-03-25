<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowGraphRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qCol' => ['nullable', 'string', 'regex:/^[a-z]+[a-z0-9_]*[a-z0-9]?$/'],
            'type' => ['nullable', 'string'],
            'categories' => ['nullable', 'array'],
            'categories.*.catNo' => ['required_with:categories', 'integer'],
            'filter' => ['nullable', 'array'],
            'filter.colname' => ['required_with:filter', 'string'],
            'filter.value' => ['required_with:filter', 'integer'],
            'subQuestions' => ['nullable', 'array'],
            'subQuestions.*.qCol' => ['required_with:subQuestions', 'string'],
            'subQuestions.*.type' => ['required_with:subQuestions', 'string'],
            'subQuestions.*.categories' => ['nullable', 'array'],
            'subQuestions.*.categories.*.catNo' => ['required_with:subQuestions.*.categories', 'integer'],
        ];
    }
}
