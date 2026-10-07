<?php

namespace Tests\Feature\Api\V1\Dashboard;

use App\Models\IndicatorVersion;
use App\Models\PlanningDocument;
use App\Models\Realization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesDashboardFixtures;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class DashboardAchievementIndicatorsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesRealizationFixtures;
    use CreatesDashboardFixtures;

    private const URL = '/api/v1/dashboard/achievement/indicators';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function officialKey(
        IndicatorVersion $iv,
        PlanningDocument $doc,
        string $period,
        array $options = [],
        ?User $owner = null,
        ?Carbon $at = null,
    ): Realization {
        $target = $this->makeDashTarget($iv, $doc, $period);

        return $this->makeDashOfficial($target, $at ?? Carbon::parse('2026-01-10 08:00:00'), $owner, $options);
    }

    // --- Akses ---------------------------------------------------------------

    public function test_unauthenticated_request_gets_401(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
    }

    public function test_only_row_level_scopes_can_read(): void
    {
        foreach (['super_admin', 'admin', 'sekretaris', 'kepala_dinas', 'operator'] as $role) {
            Sanctum::actingAs($this->createUserWithRole($role));
            $this->getJson(self::URL)->assertOk()->assertJsonPath('success', true);
        }

        foreach (['kepala_sub_bidang', 'kepala_bidang', 'pimpinan', 'publik'] as $role) {
            Sanctum::actingAs($this->createUserWithRole($role));
            $this->getJson(self::URL)->assertStatus(403);
        }
    }

    // --- Isi -----------------------------------------------------------------

    public function test_returns_only_official_rows_and_latest_disahkan_wins(): void
    {
        $doc = $this->makeDashPlanningDocument();
        $naik = $this->makeDashDirection('Naik Lebih Baik');

        $ivX = $this->makeDashIndicatorVersion('persentase_capaian', $naik);
        $target = $this->makeDashTarget($ivX, $doc, 'P1');
        $this->makeDashOfficial($target, Carbon::parse('2026-01-10 08:00:00'), null, [
            'realization_value' => 60, 'achievement_pct' => 60, 'deviation' => -40,
        ]);
        $latest = $this->makeDashOfficial($target, Carbon::parse('2026-02-10 08:00:00'), null, [
            'realization_value' => 70, 'achievement_pct' => 70, 'deviation' => -30,
        ]);

        $this->makeDashRealization(
            $this->makeDashTarget($this->makeDashIndicatorVersion(), $doc, 'P1'),
            'draft'
        );
        $this->makeDashRealization(
            $this->makeDashTarget($this->makeDashIndicatorVersion(), $doc, 'P1'),
            'direkap_sekretaris'
        );

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $rows = $this->getJson(self::URL)->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame([
            'indicator_id'         => $ivX->indicator_id,
            'indicator_name'       => DB::table('indicators')->where('id', $ivX->indicator_id)->value('name'),
            'indicator_version_id' => $ivX->id,
            'period_label'         => 'P1',
            'target_value'         => '100.0000',
            'realization_id'       => $latest->id,
            'realization_value'    => '70.0000',
            'achievement_pct'      => '70.0000',
            'deviation'            => '-30.0000',
            'direction'            => 'Naik Lebih Baik',
            'formula_type'         => 'persentase_capaian',
            'target_superseded'    => false,
        ], $rows[0]);
    }

    public function test_null_achievement_and_deviation_stay_null(): void
    {
        $iv = $this->makeDashIndicatorVersion('nilai_langsung');
        $this->officialKey($iv, $this->makeDashPlanningDocument(), 'P1', [
            'achievement_pct' => null, 'deviation' => null,
        ]);

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $row = $this->getJson(self::URL)->assertOk()->json('data.0');

        $this->assertNull($row['achievement_pct']);
        $this->assertNull($row['deviation']);
        $this->assertNull($row['direction']);
        $this->assertSame('50.0000', $row['realization_value']);
    }

    public function test_row_shows_the_measured_target_value_and_superseded_flag(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();
        $rev1 = $this->makeDashTarget($iv, $doc, 'TW I', 1, false);
        $rev2 = $this->makeDashTarget($iv, $doc, 'TW I', 2, true);
        DB::table('targets')->where('id', $rev2->id)->update(['target_value' => 120]);

        $this->makeDashOfficial($rev1, Carbon::parse('2026-01-10 08:00:00'));
        $this->makeDashRealization($rev2, 'draft');

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $row = $this->getJson(self::URL)->assertOk()->json('data.0');

        $this->assertSame('100.0000', $row['target_value'], 'Harus target yang dipakai mengukur, bukan target jangkar.');
        $this->assertTrue($row['target_superseded']);
    }

    public function test_row_level_scope_is_applied_after_ranking_and_cannot_be_bypassed(): void
    {
        $opA = $this->createUserWithRole('operator');
        $opB = $this->createUserWithRole('operator');
        $target = $this->makeDashTarget($this->makeDashIndicatorVersion(), $this->makeDashPlanningDocument());

        $this->makeDashOfficial($target, Carbon::parse('2026-01-10 08:00:00'), $opA);
        $replacement = $this->makeDashOfficial($target, Carbon::parse('2026-02-10 08:00:00'), $opB);

        Sanctum::actingAs($opA);
        $this->assertSame(0, $this->getJson(self::URL)->assertOk()->json('meta.total'));

        Sanctum::actingAs($opB);
        $data = $this->getJson(self::URL)->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($replacement->id, $data[0]['realization_id']);

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));
        $this->assertSame(1, $this->getJson(self::URL)->assertOk()->json('meta.total'));
    }

    public function test_filters_apply_and_invalid_values_are_rejected(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();
        $this->officialKey($iv, $doc, 'P1');
        $this->officialKey($iv, $doc, 'P2');

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $data = $this->getJson(self::URL.'?period_label=P2')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('P2', $data[0]['period_label']);

        $this->getJson(self::URL.'?indicator_id=abc')->assertStatus(422);
    }

    // --- Pagination ----------------------------------------------------------

    public function test_pagination_follows_the_existing_contract_with_deterministic_order(): void
    {
        $doc = $this->makeDashPlanningDocument();
        $iv1 = $this->makeDashIndicatorVersion();
        $iv2 = $this->makeDashIndicatorVersion();

        $this->officialKey($iv1, $doc, 'P2');
        $this->officialKey($iv1, $doc, 'P1');
        $this->officialKey($iv2, $doc, 'P1');

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $first = $this->getJson(self::URL.'?per_page=2')->assertOk();
        $this->assertSame(
            [[$iv1->indicator_id, 'P1'], [$iv1->indicator_id, 'P2']],
            collect($first->json('data'))->map(fn ($r) => [$r['indicator_id'], $r['period_label']])->all()
        );
        $first->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2);
        $this->assertNotNull($first->json('links.next'));

        $second = $this->getJson(self::URL.'?per_page=2&page=2')->assertOk();
        $this->assertCount(1, $second->json('data'));
        $this->assertSame($iv2->indicator_id, $second->json('data.0.indicator_id'));
    }

    public function test_default_page_size_is_15_and_meta_carries_basis(): void
    {
        $this->officialKey($this->makeDashIndicatorVersion(), $this->makeDashPlanningDocument(), 'P1');

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.basis.official_rule', 'temporary_latest_disahkan')
            ->assertJsonPath('meta.basis.scope.mode', 'full')
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);
    }

    // --- Performa ------------------------------------------------------------

    public function test_query_count_does_not_grow_with_the_number_of_rows(): void
    {
        $doc = $this->makeDashPlanningDocument();
        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(self::URL)->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->officialKey($this->makeDashIndicatorVersion(), $doc, 'P0');

        $measure(); // pemanasan
        $small = $measure();

        for ($i = 1; $i <= 8; $i++) {
            $this->officialKey($this->makeDashIndicatorVersion(), $doc, 'P'.$i);
        }

        $this->assertSame($small, $measure());
    }

    public function test_per_page_follows_the_existing_validation_contract(): void
    {
        $this->officialKey($this->makeDashIndicatorVersion(), $this->makeDashPlanningDocument(), 'P1');

        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        foreach (['0', '101', 'abc', '-1'] as $value) {
            $this->getJson(self::URL.'?per_page='.$value)->assertStatus(422);
        }

        $this->getJson(self::URL.'?per_page=')->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->getJson(self::URL.'?per_page=50')->assertOk()->assertJsonPath('meta.per_page', 50);
    }
}