<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class AchievementByStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Sama dengan StorePerformanceStructureRequest.
            'level' => ['required', 'string', 'in:Tujuan,Sasaran,Program,Kegiatan,Sub Kegiatan'],
        ];
    }
}