<?php

namespace App\Http\Requests\Target;

use Illuminate\Foundation\Http\FormRequest;

class StoreTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'indicator_version_id' => ['required', 'integer', 'exists:indicator_versions,id'],
            'planning_document_id' => ['required', 'integer', 'exists:planning_documents,id'],
            'period_label'         => ['required', 'string', 'max:255'],
            'target_value'         => ['required', 'numeric'],
            'reason'               => ['nullable', 'string'],
        ];
    }
}