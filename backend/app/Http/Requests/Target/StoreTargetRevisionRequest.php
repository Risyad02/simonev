<?php

namespace App\Http\Requests\Target;

use Illuminate\Foundation\Http\FormRequest;

class StoreTargetRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_value'         => ['required', 'numeric'],
            'reason'               => ['required', 'string', 'min:1'],
            'planning_document_id' => ['sometimes', 'integer', 'exists:planning_documents,id'],
        ];
    }
}