<?php

namespace App\Http\Requests\MasterData;

use App\Rules\ValidFormulaExpression;
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
            'type' => ['sometimes', 'nullable', 'string', 'in:system,custom'],
            'expression' => [
                Rule::requiredIf(fn () => $this->input('type') === 'custom'),
                'nullable',
                'string',
                new ValidFormulaExpression(),
            ],
            'description' => ['nullable', 'string'],
        ];
    }
}