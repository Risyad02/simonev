<?php

namespace App\Http\Requests\Realization;

use Illuminate\Foundation\Http\FormRequest;

class ReturnRealizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}