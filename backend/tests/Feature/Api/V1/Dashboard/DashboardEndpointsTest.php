<?php

namespace Tests\Feature\Api\V1\Dashboard;

use App\Models\IndicatorVersion;
use App\Models\PlanningDocument;
use App\Models\Realization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesDashboardFixtures;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class DashboardEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesRealizationFixtures;
    use CreatesDashboardFixtures;

    private const SUMMARY = '/api/v1/dashboard/summary';
    private const PIPELINE = '/api/v1/dashboard/pipeline';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function actingAsRole(string $role): User
    {
        $user = $this->createUserWithRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    /** Satu kunci (target aktif) dengan satu realisasi disahkan. */
    private function officialKey(
        IndicatorVersion $iv,
        PlanningDocument $doc,
        string $period,
        ?float $pct,
        ?User $owner = null,
    ): Realization {
        $target = $this->makeDashTarget($iv, $doc, $period);

        return $this->makeDashOfficial($target, Carbon::parse('2026-01-10 08:00:00'), $owner, [
            'achievement_pct' => $pct,
        ]);
    }

    /** @return array<string, int> status => count */
    private function pipelineCounts(): array
    {
        $statuses = $this->getJson(self::PIPELINE)->assertOk()->json('data.statuses');

        return collect($statuses)->pluck('count', 'status')->all();
    }

    // --- Akses ---------------------------------------------------------------

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->getJson(self::SUMMARY)->assertStatus(401);
        $this->getJson(self::PIPELINE)->assertStatus(401);
    }

    public function test_every_dashboard_role_can_read_and_publik_cannot(): void
    {
        $allowed = [
            'super_admin', 'admin', 'operator', 'kepala_sub_bidang',
            'kepala_bidang', 'sekretaris', 'kepala_dinas', 'pimpinan',
        ];

        foreach ($allowed as $role) {
            $this->actingAsRole($role);

            foreach ([self::SUMMARY, self::PIPELINE] as $url) {
                $this->getJson($url)
                    ->assertOk()
                    ->assertJsonPath('success', true);
            }
        }

        $this->actingAsRole('publik');

        $this->getJson(self::SUMMARY)->assertStatus(403);
        $this->getJson(self::PIPELINE)->assertStatus(403);
    }

    // --- Summary -------------------------------------------------------------

    public function test_summary_averages_only_within_formula_type_and_excludes_neutral_missing_direction_and_null(): void
    {
        $doc = $this->makeDashPlanningDocument();
        $naik = $this->makeDashDirection('Naik Lebih Baik');
        $turun = $this->makeDashDirection('Turun Lebih Baik');
        $netral = $this->makeDashDirection('Netral');

        $ivA = $this->makeDashIndicatorVersion('persentase_capaian', $naik);
        $this->officialKey($ivA, $doc, 'P1', 80);
        $this->officialKey($ivA, $doc, 'P2', 100);

        $this->officialKey($this->makeDashIndicatorVersion('persentase_capaian', $netral), $doc, 'P1', 50);
        $this->officialKey($this->makeDashIndicatorVersion('persentase_capaian', null), $doc, 'P1', 70);
        $this->officialKey($this->makeDashIndicatorVersion('nilai_langsung', $naik), $doc, 'P1', null);
        $this->officialKey($this->makeDashIndicatorVersion('target_per_realisasi', $turun), $doc, 'P1', 200);

        $this->actingAsRole('kepala_dinas');

        $data = $this->getJson(self::SUMMARY)->assertOk()->json('data');

        $this->assertSame(6, $data['official']['key_count']);
        $this->assertSame(1, $data['official']['without_percentage']);
        $this->assertArrayNotHasKey('average_achievement_pct', $data['official'], 'Tidak boleh ada rata-rata campuran.');

        $groups = collect($data['official']['groups'])->keyBy('formula_type');

        $this->assertEqualsCanonicalizing(
            ['persentase_capaian', 'nilai_langsung', 'target_per_realisasi'],
            $groups->keys()->all()
        );

        $pct = $groups['persentase_capaian'];
        $this->assertSame(4, $pct['key_count']);
        $this->assertSame(0, $pct['without_percentage']);
        $this->assertSame(2, $pct['averaged_count']);
        $this->assertSame('90.0000', $pct['average_achievement_pct']);
        $this->assertSame(2, $pct['without_direction_assessment']);

        $langsung = $groups['nilai_langsung'];
        $this->assertSame(1, $langsung['key_count']);
        $this->assertSame(1, $langsung['without_percentage']);
        $this->assertSame(0, $langsung['averaged_count']);
        $this->assertNull($langsung['average_achievement_pct']);
        $this->assertSame(0, $langsung['without_direction_assessment']);

        $terbalik = $groups['target_per_realisasi'];
        $this->assertSame(1, $terbalik['key_count']);
        $this->assertSame(1, $terbalik['averaged_count']);
        $this->assertSame('200.0000', $terbalik['average_achievement_pct']);
    }

    public function test_summary_coverage_counts_only_keys_with_an_active_anchor_target(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();

        $this->officialKey($iv, $doc, 'A', 80);
        $this->officialKey($iv, $doc, 'B', 90);

        // Kunci C: target aktif, belum ada angka resmi.
        $this->makeDashRealization($this->makeDashTarget($iv, $doc, 'C'), 'draft');

        // Kunci D: hanya target tidak aktif, tetapi punya angka resmi.
        $this->makeDashOfficial(
            $this->makeDashTarget($iv, $doc, 'D', 1, false),
            Carbon::parse('2026-01-10 08:00:00')
        );

        $this->actingAsRole('kepala_dinas');

        $data = $this->getJson(self::SUMMARY)->assertOk()->json('data');

        $this->assertSame(3, $data['official']['key_count']);
        $this->assertSame(3, $data['coverage']['universe_keys']);
        $this->assertSame(2, $data['coverage']['official_keys']);
        $this->assertEqualsWithDelta(66.67, $data['coverage']['percentage'], 0.001);
    }

    public function test_summary_scope_limits_operator_and_reviewer_and_hides_coverage(): void
    {
        $opA = $this->createUserWithRole('operator');
        $opB = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();

        $this->officialKey($iv, $doc, 'P1', 80, $opA);
        $r2 = $this->officialKey($iv, $doc, 'P2', 90, $opB);
        $this->makeDashHistory($r2, 'diajukan', 'divalidasi_kasubbid', $kasubbid, Carbon::now());

        Sanctum::actingAs($opA);
        $data = $this->getJson(self::SUMMARY)->assertOk()->json('data');
        $this->assertSame(1, $data['official']['key_count']);
        $this->assertNull($data['coverage']);

        Sanctum::actingAs($opB);
        $this->assertSame(1, $this->getJson(self::SUMMARY)->json('data.official.key_count'));

        Sanctum::actingAs($kasubbid);
        $data = $this->getJson(self::SUMMARY)->assertOk()->json('data');
        $this->assertSame(1, $data['official']['key_count'], 'Kasubbid hanya melihat yang pernah ia proses.');
        $this->assertNull($data['coverage']);

        $this->actingAsRole('kepala_dinas');
        $data = $this->getJson(self::SUMMARY)->assertOk()->json('data');
        $this->assertSame(2, $data['official']['key_count']);
        $this->assertNotNull($data['coverage']);
    }

    public function test_summary_exposes_meta_basis(): void
    {
        $this->actingAsRole('kepala_dinas');

        $this->getJson(self::SUMMARY)
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'message', 'meta' => ['basis' => ['generated_at']]])
            ->assertJsonPath('meta.basis.official_rule', 'temporary_latest_disahkan')
            ->assertJsonPath('meta.basis.key', 'indicator_version_id+period_label')
            ->assertJsonPath('meta.basis.scope.mode', 'full')
            ->assertJsonPath('meta.basis.scope.transitional', false)
            ->assertJsonPath('meta.basis.filters', []);

        $this->actingAsRole('operator');

        $this->getJson(self::SUMMARY)
            ->assertOk()
            ->assertJsonPath('meta.basis.scope.mode', 'own-scope')
            ->assertJsonPath('meta.basis.scope.transitional', true);
    }

    public function test_summary_applies_filters_and_rejects_invalid_values(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();
        $this->officialKey($iv, $doc, 'P1', 80);
        $this->officialKey($iv, $doc, 'P2', 90);

        $this->actingAsRole('kepala_dinas');

        $this->getJson(self::SUMMARY.'?period_label=P2')
            ->assertOk()
            ->assertJsonPath('data.official.key_count', 1)
            ->assertJsonPath('meta.basis.filters.period_label', 'P2');

        $this->getJson(self::SUMMARY.'?indicator_id=abc')->assertStatus(422);
    }

    // --- Pipeline ------------------------------------------------------------

    public function test_pipeline_lists_all_seven_statuses_in_fixed_order(): void
    {
        $target = $this->makeDashTarget($this->makeDashIndicatorVersion(), $this->makeDashPlanningDocument());

        $this->makeDashRealization($target, 'draft');
        $this->makeDashRealization($target, 'draft');
        $this->makeDashRealization($target, 'diajukan', null, ['updated_at' => Carbon::parse('2026-03-01 00:00:00')]);
        $this->makeDashRealization($target, 'diajukan', null, ['updated_at' => Carbon::parse('2026-02-01 00:00:00')]);
        $this->makeDashRealization($target, 'divalidasi_kasubbid');
        $this->makeDashRealization($target, 'direkap_sekretaris');
        $this->makeDashOfficial($target, Carbon::parse('2026-01-10 08:00:00'));

        $this->actingAsRole('kepala_dinas');

        $response = $this->getJson(self::PIPELINE)->assertOk();
        $statuses = collect($response->json('data.statuses'));

        $this->assertSame(
            ['draft', 'diajukan', 'divalidasi_kasubbid', 'divalidasi_kabid', 'direkap_sekretaris', 'dikembalikan', 'disahkan'],
            $statuses->pluck('status')->all()
        );
        $this->assertSame(
            ['status', 'label', 'is_final', 'count', 'oldest_updated_at'],
            array_keys($statuses->first())
        );

        $byStatus = $statuses->keyBy('status');
        $this->assertSame(2, $byStatus['draft']['count']);
        $this->assertSame(2, $byStatus['diajukan']['count']);
        $this->assertSame(0, $byStatus['divalidasi_kabid']['count']);
        $this->assertFalse($byStatus['direkap_sekretaris']['is_final']);
        $this->assertTrue($byStatus['disahkan']['is_final']);
        $this->assertSame(7, $response->json('data.total'));

        $this->assertTrue(
            Carbon::parse($byStatus['diajukan']['oldest_updated_at'])->equalTo(Carbon::parse('2026-02-01 00:00:00'))
        );
        $this->assertNull($byStatus['dikembalikan']['oldest_updated_at']);

        $response->assertJsonPath('meta.basis.scope.mode', 'full');
    }

    public function test_pipeline_is_limited_by_the_same_read_scope(): void
    {
        $opA = $this->createUserWithRole('operator');
        $opB = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $target = $this->makeDashTarget($this->makeDashIndicatorVersion(), $this->makeDashPlanningDocument());

        $this->makeDashRealization($target, 'draft', $opA);
        $this->makeDashRealization($target, 'draft', $opB);
        $this->makeDashRealization($target, 'diajukan', $opB);
        $this->makeDashRealization($target, 'direkap_sekretaris', $opB);

        Sanctum::actingAs($opA);
        $counts = $this->pipelineCounts();
        $this->assertSame(1, $counts['draft']);
        $this->assertSame(0, $counts['diajukan']);

        Sanctum::actingAs($kasubbid);
        $counts = $this->pipelineCounts();
        $this->assertSame(0, $counts['draft']);
        $this->assertSame(1, $counts['diajukan'], 'Tahap Kasubbid adalah diajukan.');
        $this->assertSame(0, $counts['direkap_sekretaris']);
    }
}