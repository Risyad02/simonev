<?php

namespace Database\Factories;

use App\Models\Indicator;
use App\Models\PerformanceStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Indicator>
 */
class IndicatorFactory extends Factory
{
    protected $model = Indicator::class;

    public function definition(): array
    {
        return [
            'structure_id' => PerformanceStructure::factory(),
            'name' => fake()->sentence(3),
        ];
    }
}