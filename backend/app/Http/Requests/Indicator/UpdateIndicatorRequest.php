<?php

namespace App\Http\Requests\Indicator;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'structure_id' => ['sometimes', 'required', 'integer', 'exists:performance_structure,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}