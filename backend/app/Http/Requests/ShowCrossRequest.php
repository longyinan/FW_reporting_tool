<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowCrossRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sideQno' => ['required', 'string', 'regex:/^[A-Z]+[A-Z0-9_]*[A-Z0-9]?$/'],
            'headQno' => ['required', 'string', 'regex:/^[A-Z]+[A-Z0-9_]*[A-Z0-9]?$/'],
            'filter' => ['nullable', 'array'],
            'filter.colname' => ['required_with:filter', 'string', 'regex:/^[a-z]+[a-z0-9_]*[a-z0-9]?$/'],
            'filter.value' => ['required_with:filter', 'integer'],
        ];
    }
}
