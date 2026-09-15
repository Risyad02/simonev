<?php

namespace Database\Factories;

use App\Models\Target;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Target>
 *
 * Catatan: indicator_version_id dan planning_document_id TIDAK memiliki
 * default factory otomatis (IndicatorVersionFactory sengaja tidak dibuat,
 * mengikuti pola testing Phase 8 — lihat IndicatorTest.php yang membuat
 * IndicatorVersion via Model::create() inline melalui helper method privat,
 * bukan factory chain). Pemanggil WAJIB meng-override kedua field ini,
 * misalnya:
 *
 *   Target::factory()->create([
 *       'indicator_version_id' => $this->createIndicatorVersion()->id,
 *       'planning_document_id' => $planningDocument->id,
 *   ]);
 */
class TargetFactory extends Factory
{
    protected $model = Target::class;

    public function definition(): array
    {
        return [
            'indicator_version_id' => null, // WAJIB di-override oleh pemanggil
            'planning_document_id' => null, // WAJIB di-override oleh pemanggil
            'period_label'         => fake()->randomElement(['Triwulan I 2026', 'Triwulan II 2026', '2026']),
            'target_value'         => fake()->randomFloat(4, 1, 1000),
            'revision_no'          => 1,
            'is_active'            => true,
            'valid_from'           => now(),
            'valid_to'             => null,
            'reason'               => null,
            'created_by'           => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'valid_to'  => now(),
        ]);
    }

    public function revision(int $revisionNo): static
    {
        return $this->state(fn (array $attributes) => [
            'revision_no' => $revisionNo,
        ]);
    }
}