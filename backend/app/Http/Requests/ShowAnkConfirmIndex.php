<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowAnkConfirmIndex extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sampleNo' => ['required', 'integer'],
            'qNo' => ['required', 'array'],
        ];
    }
}
