<?php

namespace Database\Factories;

use App\Models\PlanningDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanningDocument>
 */
class PlanningDocumentFactory extends Factory
{
    protected $model = PlanningDocument::class;

    public function definition(): array
    {
        return [
            'parent_document_id' => null,
            'document_type' => 'Renstra',
            'year' => (int) date('Y'),
            'period_start_year' => (int) date('Y'),
            'period_end_year' => (int) date('Y') + 4,
            'version_no' => 1,
            'status' => 'active',
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}