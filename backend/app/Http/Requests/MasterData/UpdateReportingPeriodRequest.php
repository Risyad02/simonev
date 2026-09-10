<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reportingPeriod = $this->route('reportingPeriod');
        $id = $reportingPeriod?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('reporting_periods', 'name')->ignore($id)],
            'periods_per_year' => ['sometimes', 'required', 'integer', 'min:1', 'max:255'],
        ];
    }
}