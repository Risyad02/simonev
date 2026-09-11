<?php

namespace Database\Factories;

use App\Models\PerformanceStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceStructure>
 */
class PerformanceStructureFactory extends Factory
{
    protected $model = PerformanceStructure::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'planning_document_id' => null,
            'level_type' => 'Tujuan',
            'name' => fake()->sentence(3),
            'year' => (int) date('Y'),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}