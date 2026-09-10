<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

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
            'expression' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ];
    }
}