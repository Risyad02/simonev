<?php

namespace Database\Factories;

use App\Enums\RealizationStatus;
use App\Models\Realization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Realization>
 *
 * Catatan: target_id WAJIB di-override oleh pemanggil (rantai Target
 * memerlukan IndicatorVersion yang dibuat inline, lihat trait
 * Tests\Concerns\CreatesRealizationFixtures). input_by default null;
 * gunakan ownedBy() bila test membutuhkan pemilik data.
 */
class RealizationFactory extends Factory
{
    protected $model = Realization::class;

    public function definition(): array
    {
        return [
            'target_id'         => null, // WAJIB di-override oleh pemanggil
            'realization_value' => fake()->randomFloat(4, 1, 1000),
            'achievement_pct'   => null,
            'deviation'         => null,
            'status'            => RealizationStatus::Draft->value,
            'input_by'          => null,
            'input_at'          => now(),
        ];
    }

    public function withStatus(RealizationStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status->value]);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => ['input_by' => $user->id]);
    }
}