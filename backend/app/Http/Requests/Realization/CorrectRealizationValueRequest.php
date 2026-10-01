<?php

namespace App\Http\Requests\Realization;

use Illuminate\Foundation\Http\FormRequest;

class CorrectRealizationValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'realization_value' => ['required', 'numeric'],
            'reason'            => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}