<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:reporting_periods,name'],
            // max:255 = technical safety bound (kolom unsignedTinyInteger), BUKAN business rule — status TBD, lihat Open Decisions.
            'periods_per_year' => ['required', 'integer', 'min:1', 'max:255'],
        ];
    }
}