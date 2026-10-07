<?php

namespace Tests\Feature\Api\V1\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesDashboardFixtures;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class DashboardAchievementByStructureTest extends TestCase
{
    use RefreshDatabase;
    use CreatesRealizationFixtures;
    use CreatesDashboardFixtures;

    private const URL = '/api/v1/dashboard/achievement/by-structure';

    /** @var array<string, int> id node struktur menurut nama */
    private array $nodes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    /**
     * T1 > S1 > P1 > K1 > SK1
     *           > P2 > K2
     *           > P3
     * Indikator:
     *  a @K1  : P1 resmi 80; P2 hanya draft
     *  b @SK1 : P1 resmi dengan capaian null
     *  c @P2  : P1 target aktif, tanpa realisasi
     *  d @S1  : P1 resmi 90 (di atas level Program dan Kegiatan)
     */
    private function seedTree(): void
    {
        $n = &$this->nodes;
        $n['T1'] = $this->makeDashStructureNode('Tujuan', 'T1');
        $n['S1'] = $this->makeDashStructureNode('Sasaran', 'S1', $n['T1']);
        $n['P1'] = $this->makeDashStructureNode('Program', 'P1', $n['S1']);
        $n['K1'] = $this->makeDashStructureNode('Kegiatan', 'K1', $n['P1']);
        $n['SK1'] = $this->makeDashStructureNode('Sub Kegiatan', 'SK1', $n['K1']);
        $n['P2'] = $this->makeDashStructureNode('Program', 'P2', $n['S1']);
        $n['K2'] = $this->makeDashStructureNode('Kegiatan', 'K2', $n['P2']);
        $n['P3'] = $this->makeDashStructureNode('Program', 'P3', $n['S1']);

        $doc = $this->makeDashPlanningDocument();
        $at = Carbon::parse('2026-01-10 08:00:00');

        $a = $this->makeDashIndicatorVersion('persentase_capaian', null, $n['K1']);
        $this->makeDashOfficial($this->makeDashTarget($a, $doc, 'P1'), $at, null, ['achievement_pct' => 80]);
        $this->makeDashRealization($this->makeDashTarget($a, $doc, 'P2'), 'draft');

        $b = $this->makeDashIndicatorVersion('persentase_capaian', null, $n['SK1']);
        $this->makeDashOfficial($this->makeDashTarget($b, $doc, 'P1'), $at, null, ['achievement_pct' => null]);

        $c = $this->makeDashIndicatorVersion('persentase_capaian', null, $n['P2']);
        $this->makeDashTarget($c, $doc, 'P1');

        $d = $this->makeDashIndicatorVersion('persentase_capaian', null, $n['S1']);
        $this->makeDashOfficial($this->makeDashTarget($d, $doc, 'P1'), $at, null, ['achievement_pct' => 90]);
    }

    private function entry(int $indicators, int $withOfficial, int $universe, int $official, ?float $pct, int $withoutPct): array
    {
        return [
            'indicator_count'          => $indicators,
            'indicators_with_official' => $withOfficial,
            'coverage'                 => ['universe_keys' => $universe, 'official_keys' => $official, 'percentage' => $pct],
            'without_percentage'       => $withoutPct,
        ];
    }

    /** JSON dapat mengirim 0.0 sebagai 0; bandingkan sebagai float. */
    private function normalize(array $entry): array
    {
        if ($entry['coverage']['percentage'] !== null) {
            $entry['coverage']['percentage'] = (float) $entry['coverage']['percentage'];
        }

        return $entry;
    }

    // --- Akses dan validasi --------------------------------------------------

    public function test_unauthenticated_request_gets_401(): void
    {
        $this->getJson(self::URL.'?level=Program')->assertStatus(401);
    }

    public function test_only_unscoped_scopes_can_read(): void
    {
        foreach (['super_admin', 'admin', 'sekretaris', 'kepala_dinas', 'pimpinan'] as $role) {
            Sanctum::actingAs($this->createUserWithRole($role));
            $this->getJson(self::URL.'?level=Program')->assertOk()->assertJsonPath('success', true);
        }

        foreach (['operator', 'kepala_sub_bidang', 'kepala_bidang', 'publik'] as $role) {
            Sanctum::actingAs($this->createUserWithRole($role));
            $this->getJson(self::URL.'?level=Program')->assertStatus(403);
        }
    }

    public function test_level_is_required_and_must_be_a_known_level(): void
    {
        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $this->getJson(self::URL)->assertStatus(422);
        $this->getJson(self::URL.'?level=Bukan Level')->assertStatus(422);
    }

    // --- Isi -----------------------------------------------------------------

    public function test_program_level_aggregates_descendants_and_reports_unattributed(): void
    {
        $this->seedTree();
        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $data = $this->getJson(self::URL.'?level=Program')->assertOk()->json('data');

        $this->assertSame('Program', $data['level']);
        $this->assertSame(
            [$this->nodes['P1'], $this->nodes['P2'], $this->nodes['P3']],
            array_column($data['nodes'], 'structure_id')
        );

        $byName = collect($data['nodes'])->keyBy('name');

        $p1 = $byName['P1'];
        $this->assertSame('Program', $p1['level_type']);
        $this->assertSame(
            $this->entry(2, 2, 3, 2, 66.67, 1),
            $this->normalize(array_diff_key($p1, array_flip(['structure_id', 'name', 'level_type'])))
        );

        $this->assertSame(
            $this->entry(1, 0, 1, 0, 0.0, 0),
            $this->normalize(array_diff_key($byName['P2'], array_flip(['structure_id', 'name', 'level_type'])))
        );

        $this->assertSame(
            $this->entry(0, 0, 0, 0, null, 0),
            $this->normalize(array_diff_key($byName['P3'], array_flip(['structure_id', 'name', 'level_type'])))
        );

        $this->assertSame(
            $this->entry(1, 1, 1, 1, 100.0, 0),
            $this->normalize($data['unattributed'])
        );
    }

    public function test_kegiatan_level_treats_indicators_above_the_level_as_unattributed(): void
    {
        $this->seedTree();
        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $data = $this->getJson(self::URL.'?level=Kegiatan')->assertOk()->json('data');
        $byName = collect($data['nodes'])->keyBy('name');

        $this->assertSame(['K1', 'K2'], $byName->keys()->all());
        $this->assertSame(
            $this->entry(2, 2, 3, 2, 66.67, 1),
            $this->normalize(array_diff_key($byName['K1'], array_flip(['structure_id', 'name', 'level_type'])))
        );
        $this->assertSame($this->entry(0, 0, 0, 0, null, 0), $this->normalize(array_diff_key($byName['K2'], array_flip(['structure_id', 'name', 'level_type']))));
        $this->assertSame($this->entry(2, 1, 2, 1, 50.0, 0), $this->normalize($data['unattributed']));
    }

    public function test_there_is_no_cross_indicator_average_anywhere(): void
    {
        $this->seedTree();
        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $json = $this->getJson(self::URL.'?level=Program')->assertOk()->getContent();

        $this->assertStringNotContainsString('average', $json);
    }

    public function test_strategic_summary_sees_the_same_aggregates(): void
    {
        $this->seedTree();

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));
        $full = $this->getJson(self::URL.'?level=Program')->assertOk()->json('data');

        Sanctum::actingAs($this->createUserWithRole('pimpinan'));
        $strategic = $this->getJson(self::URL.'?level=Program')->assertOk()->json('data');

        $this->assertSame($full, $strategic);
    }

    public function test_response_carries_meta_basis(): void
    {
        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $this->getJson(self::URL.'?level=Program')
            ->assertOk()
            ->assertJsonPath('meta.basis.official_rule', 'temporary_latest_disahkan')
            ->assertJsonPath('meta.basis.scope.mode', 'full')
            ->assertJsonPath('meta.basis.filters', []);
    }

    public function test_nested_same_level_attributes_each_indicator_to_the_nearest_ancestor(): void
    {
        $tujuan = $this->makeDashStructureNode('Tujuan', 'T');
        $py = $this->makeDashStructureNode('Program', 'PY', $tujuan);
        $pz = $this->makeDashStructureNode('Program', 'PZ', $py);
        $kz = $this->makeDashStructureNode('Kegiatan', 'KZ', $pz);

        $f = $this->makeDashIndicatorVersion('persentase_capaian', null, $kz);
        $this->makeDashOfficial(
            $this->makeDashTarget($f, $this->makeDashPlanningDocument(), 'P1'),
            Carbon::parse('2026-01-10 08:00:00'),
            null,
            ['achievement_pct' => 70]
        );

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $data = $this->getJson(self::URL.'?level=Program')->assertOk()->json('data');
        $byName = collect($data['nodes'])->keyBy('name');
        $strip = ['structure_id', 'name', 'level_type'];

        $this->assertSame(['PY', 'PZ'], $byName->keys()->all());
        $this->assertSame($this->entry(0, 0, 0, 0, null, 0), $this->normalize(array_diff_key($byName['PY'], array_flip($strip))));
        $this->assertSame($this->entry(1, 1, 1, 1, 100.0, 0), $this->normalize(array_diff_key($byName['PZ'], array_flip($strip))));
        $this->assertSame($this->entry(0, 0, 0, 0, null, 0), $this->normalize($data['unattributed']));
    }
}