<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class DashboardFilterRequest extends FormRequest
{
    private const INTEGER_FILTERS = [
        'planning_document_id',
        'indicator_id',
        'reporting_period_id',
        'direction_id',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'planning_document_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'indicator_id'         => ['sometimes', 'nullable', 'integer', 'min:1'],
            'reporting_period_id'  => ['sometimes', 'nullable', 'integer', 'min:1'],
            'direction_id'         => ['sometimes', 'nullable', 'integer', 'min:1'],
            'period_label'         => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Filter tervalidasi tanpa nilai kosong, dengan id sebagai integer.
     *
     * @return array<string, int|string>
     */
    public function filters(): array
    {
        $filters = array_filter($this->validated(), fn ($value) => $value !== null);

        foreach (self::INTEGER_FILTERS as $name) {
            if (isset($filters[$name])) {
                $filters[$name] = (int) $filters[$name];
            }
        }

        return $filters;
    }
}