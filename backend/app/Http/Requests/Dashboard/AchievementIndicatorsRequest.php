<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Support\Arr;

class AchievementIndicatorsRequest extends DashboardFilterRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** Hanya filter dashboard; per_page bukan filter. */
    public function filters(): array
    {
        return Arr::except(parent::filters(), ['per_page']);
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?: 15);
    }
}