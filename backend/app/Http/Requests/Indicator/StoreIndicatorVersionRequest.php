<?php

namespace App\Http\Requests\Indicator;

use Illuminate\Foundation\Http\FormRequest;

class StoreIndicatorVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'formula_id' => ['required', 'integer', 'exists:formulas,id'],
            'reporting_period_id' => ['required', 'integer', 'exists:reporting_periods,id'],
            'direction_id' => ['nullable', 'integer', 'exists:measurement_directions,id'],
            'planning_document_id' => ['nullable', 'integer', 'exists:planning_documents,id'],
            'operational_definition' => ['nullable', 'string'],
            'measurement_method' => ['nullable', 'string'],
            'data_source' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['nullable', 'date'],
        ];
    }
}