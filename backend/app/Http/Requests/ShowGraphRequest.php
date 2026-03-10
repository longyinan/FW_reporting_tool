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
            'qCol' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'categories' => ['nullable', 'array'],
            'categories.*.catNo' => ['required_with:categories', 'integer'],
            'subQuestions' => ['nullable', 'array'],
            'subQuestions.*.qCol' => ['required_with:subQuestions', 'string'],
            'subQuestions.*.type' => ['required_with:subQuestions', 'string'],
            'subQuestions.*.categories' => ['nullable', 'array'],
            'subQuestions.*.categories.*.catNo' => ['required_with:subQuestions.*.categories', 'integer'],
        ];
    }
}
