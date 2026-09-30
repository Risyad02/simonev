<?php

namespace App\Http\Requests\Realization;

use Illuminate\Foundation\Http\FormRequest;

class ApproveRealizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}