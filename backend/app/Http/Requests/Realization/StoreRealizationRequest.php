<?php

namespace App\Http\Requests\Realization;

use Illuminate\Foundation\Http\FormRequest;

class StoreRealizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_id'         => ['required', 'integer', 'exists:targets,id'],
            'realization_value' => ['required', 'numeric'],
        ];
    }
}