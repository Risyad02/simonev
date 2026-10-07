<?php

namespace Tests\Feature\Services\Realization;

use App\Enums\RealizationStatus;
use App\Models\Realization;
use App\Services\Realization\RealizationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesDashboardFixtures;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

/**
 * Menjaga applyReadScope (bentuk query) tetap setara dengan canView (bentuk
 * per-objek) untuk setiap role. TBD-4 kelak menambah predikat unit di KEDUANYA.
 */
class RealizationReadScopeParityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesRealizationFixtures;
    use CreatesDashboardFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_apply_read_scope_matches_can_view_for_every_role(): void
    {
        $access = app(RealizationAccessService::class);
        $other = $this->createUserWithRole('operator');
        $target = $this->createActiveTargetWithFormula();

        $roles = [
            'super_admin', 'admin', 'operator', 'kepala_sub_bidang',
            'kepala_bidang', 'sekretaris', 'kepala_dinas', 'pimpinan', 'publik',
        ];

        foreach ($roles as $role) {
            $actor = $this->createUserWithRole($role);

            foreach (RealizationStatus::cases() as $status) {
                $this->makeDashRealization($target, $status->value, $other);   // bukan pemilik
                $this->makeDashRealization($target, $status->value, $actor);   // pemilik
                $acted = $this->makeDashRealization($target, $status->value, $other);
                $this->makeDashHistory($acted, 'diajukan', $status->value, $actor, Carbon::now());
            }

            $expected = Realization::query()->get()
                ->filter(fn (Realization $r): bool => $access->canView($r, $actor))
                ->pluck('id')->sort()->values()->all();

            $actual = $access->applyReadScope(Realization::query(), $actor)
                ->pluck('id')->sort()->values()->all();

            $this->assertSame($expected, $actual, "Paritas gagal untuk role {$role}.");

            if (in_array($role, ['operator', 'kepala_sub_bidang'], true)) {
                $this->assertNotEmpty($expected, "Matriks uji harus menghasilkan baris untuk {$role}.");
            }
        }
    }
}