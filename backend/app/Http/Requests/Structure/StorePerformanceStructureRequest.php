<?php

namespace App\Http\Requests\Structure;

use Illuminate\Foundation\Http\FormRequest;

class StorePerformanceStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:performance_structure,id'],
            'planning_document_id' => ['nullable', 'integer', 'exists:planning_documents,id'],
            'level_type' => ['required', 'string', 'in:Tujuan,Sasaran,Program,Kegiatan,Sub Kegiatan'],
            'name' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}