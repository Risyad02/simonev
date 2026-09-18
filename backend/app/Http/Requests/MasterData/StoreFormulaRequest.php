<?php

namespace App\Http\Requests\MasterData;

use App\Rules\ValidFormulaExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:formulas,name'],
            'formula_type' => ['required', 'string', 'max:100'],
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