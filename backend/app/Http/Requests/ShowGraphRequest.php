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
            'question' => ['required', 'array'],
            'question.type' => ['required', 'string'],
            'question.qNo' => ['nullable', 'string'],
            'question.qCol' => ['nullable', 'string'],
            'question.name' => ['nullable', 'string'],
            'question.categories' => ['nullable', 'array'],
            'question.categories.*.catNo' => ['nullable', 'integer'],
            'question.subQuestions' => ['nullable', 'array'],
            'question.subQuestions.*' => ['array'],
        ];
    }
}

