<?php

namespace App\Http\Requests\Indicator;

use Illuminate\Foundation\Http\FormRequest;

class StoreIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'structure_id' => ['required', 'integer', 'exists:performance_structure,id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}