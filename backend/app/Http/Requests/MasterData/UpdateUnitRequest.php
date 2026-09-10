<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unit = $this->route('unit');
        $id = $unit?->id;

        return [
            'parent_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('units', 'code')->ignore($id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'unit_type' => ['nullable', 'string', 'max:100'],
        ];
    }
}