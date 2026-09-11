<?php

namespace App\Http\Requests\Structure;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformanceStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $current = $this->route('performanceStructure');
        $id = $current?->id;

        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:performance_structure,id',
                function ($attribute, $value, $fail) use ($id) {
                    if ($value !== null && $id !== null && (int) $value === (int) $id) {
                        $fail('Parent tidak boleh merujuk ke dirinya sendiri.');
                    }
                },
            ],
            'planning_document_id' => ['nullable', 'integer', 'exists:planning_documents,id'],
            'level_type' => ['sometimes', 'required', 'string', 'in:Tujuan,Sasaran,Program,Kegiatan,Sub Kegiatan'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}