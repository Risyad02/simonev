<?php

namespace Tests\Feature\Api\V1\Dashboard;

use App\Models\IndicatorVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesDashboardFixtures;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class DashboardDataQualityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesRealizationFixtures;
    use CreatesDashboardFixtures;

    private const URL = '/api/v1/dashboard/data-quality';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    /**
     * Skenario dengan hasil yang dapat dihitung tangan (kunci = indikator-versi + periode):
     *  K1 ivA/A1  target aktif, resmi 80
     *  K2 ivB/B1  target aktif, resmi 50 (Naik + target_per_realisasi = tidak cocok)
     *  K3 ivC/C1  target aktif, resmi 60 (Turun + persentase_capaian = tidak cocok)
     *  K4 ivD/D1  target aktif, resmi dengan capaian null (arah Netral)
     *  K5 ivE/E1  target aktif, hanya draft; versi tanpa arah
     *  K6 ivA/A2  target aktif, DUA realisasi disahkan
     *  K7 ivA/A3  DUA target aktif, tanpa realisasi
     *  K8 ivA/A4  hanya target tidak aktif, tanpa realisasi (di luar semesta)
     *  K9 ivF/F1  target aktif, tanpa realisasi, versi indikator tidak aktif
     *  K10 ivA2/A1 target aktif, tanpa realisasi; indikator sama dengan K1 (terpecah antar versi)
     */
    private function seedQualityScenario(): void
    {
        $doc = $this->makeDashPlanningDocument();
        $naik = $this->makeDashDirection('Naik Lebih Baik');
        $turun = $this->makeDashDirection('Turun Lebih Baik');
        $netral = $this->makeDashDirection('Netral');
        $at = Carbon::parse('2026-01-10 08:00:00');

        $ivA = $this->makeDashIndicatorVersion('persentase_capaian', $naik);
        $this->makeDashOfficial($this->makeDashTarget($ivA, $doc, 'A1'), $at, null, ['achievement_pct' => 80]);

        $ivB = $this->makeDashIndicatorVersion('target_per_realisasi', $naik);
        $this->makeDashOfficial($this->makeDashTarget($ivB, $doc, 'B1'), $at, null, ['achievement_pct' => 50]);

        $ivC = $this->makeDashIndicatorVersion('persentase_capaian', $turun);
        $this->makeDashOfficial($this->makeDashTarget($ivC, $doc, 'C1'), $at, null, ['achievement_pct' => 60]);

        $ivD = $this->makeDashIndicatorVersion('persentase_capaian', $netral);
        $this->makeDashOfficial($this->makeDashTarget($ivD, $doc, 'D1'), $at, null, ['achievement_pct' => null]);

        $ivE = $this->makeDashIndicatorVersion('persentase_capaian', null);
        $this->makeDashRealization($this->makeDashTarget($ivE, $doc, 'E1'), 'draft');

        $a2 = $this->makeDashTarget($ivA, $doc, 'A2');
        $this->makeDashOfficial($a2, $at);
        $this->makeDashOfficial($a2, $at->copy()->addDay());

        $this->makeDashTarget($ivA, $doc, 'A3', 1, true);
        $this->makeDashTarget($ivA, $doc, 'A3', 2, true);

        $this->makeDashTarget($ivA, $doc, 'A4', 1, false);

        $ivF = $this->makeDashIndicatorVersion('persentase_capaian', $naik);
        $ivF->update(['is_active' => false]);
        $this->makeDashTarget($ivF, $doc, 'F1');

        $ivA2 = IndicatorVersion::create([
            'indicator_id'        => $ivA->indicator_id,
            'unit_of_measure_id'  => $ivA->unit_of_measure_id,
            'formula_id'          => $ivA->formula_id,
            'reporting_period_id' => $ivA->reporting_period_id,
            'direction_id'        => $ivA->direction_id,
            'is_active'           => true,
            'valid_from'          => now(),
        ]);
        $this->makeDashTarget($ivA2, $doc, 'A1');
    }

    // --- Akses ---------------------------------------------------------------

    public function test_unauthenticated_request_gets_401(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
    }

    public function test_only_full_cross_unit_and_operational_scopes_can_read(): void
    {
        foreach (['super_admin', 'kepala_dinas', 'sekretaris', 'admin'] as $role) {
            Sanctum::actingAs($this->createUserWithRole($role));
            $this->getJson(self::URL)->assertOk()->assertJsonPath('success', true);
        }

        foreach (['operator', 'kepala_sub_bidang', 'kepala_bidang', 'pimpinan', 'publik'] as $role) {
            Sanctum::actingAs($this->createUserWithRole($role));
            $this->getJson(self::URL)->assertStatus(403);
        }
    }

    // --- Isi -----------------------------------------------------------------

    public function test_empty_database_returns_integer_zeros(): void
    {
        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $this->assertSame([
            'keys' => [
                'universe_keys'                 => 0,
                'universe_without_official'     => 0,
                'conflicting_official'          => 0,
                'multiple_active_targets'       => 0,
                'without_active_target'         => 0,
                'on_inactive_indicator_version' => 0,
                'split_across_versions'         => 0,
            ],
            'official' => [
                'without_percentage'         => 0,
                'direction_formula_mismatch' => 0,
            ],
            'indicator_versions' => [
                'without_direction' => 0,
            ],
        ], $this->getJson(self::URL)->assertOk()->json('data'));
    }

    public function test_counts_match_the_hand_computed_scenario(): void
    {
        $this->seedQualityScenario();

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $this->assertSame([
            'keys' => [
                'universe_keys'                 => 9,
                'universe_without_official'     => 4,
                'conflicting_official'          => 1,
                'multiple_active_targets'       => 1,
                'without_active_target'         => 1,
                'on_inactive_indicator_version' => 1,
                'split_across_versions'         => 1,
            ],
            'official' => [
                'without_percentage'         => 1,
                'direction_formula_mismatch' => 2,
            ],
            'indicator_versions' => [
                'without_direction' => 1,
            ],
        ], $this->getJson(self::URL)->assertOk()->json('data'));
    }

    public function test_response_exposes_meta_basis_without_filters(): void
    {
        Sanctum::actingAs($this->createUserWithRole('admin'));

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'message', 'meta' => ['basis' => ['generated_at']]])
            ->assertJsonPath('meta.basis.official_rule', 'temporary_latest_disahkan')
            ->assertJsonPath('meta.basis.scope.mode', 'operational')
            ->assertJsonPath('meta.basis.filters', []);
    }
}