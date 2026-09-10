<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $formula = $this->route('formula');
        $id = $formula?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('formulas', 'name')->ignore($id)],
            'formula_type' => ['sometimes', 'required', 'string', 'max:100'],
            'expression' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ];
    }
}