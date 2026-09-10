<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitOfMeasure = $this->route('unitOfMeasure');
        $id = $unitOfMeasure?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('units_of_measure', 'name')->ignore($id)],
            'symbol' => ['nullable', 'string', 'max:50'],
        ];
    }
}