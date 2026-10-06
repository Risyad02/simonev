<?php

namespace Tests\Feature\Services\Dashboard;

use App\Services\Dashboard\DashboardScopeService;
use App\Services\Dashboard\OfficialRealizationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\CreatesDashboardFixtures;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class OfficialRealizationQueryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesRealizationFixtures;
    use CreatesDashboardFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function official(): OfficialRealizationQuery
    {
        return app(OfficialRealizationQuery::class);
    }

    /** Smoke test fixture: harus LULUS sebelum implementasi ada. */
    public function test_fixture_builders_produce_consistent_data(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();
        $target = $this->makeDashTarget($iv, $doc, 'P1', 1);
        $realization = $this->makeDashOfficial($target, Carbon::parse('2026-01-10 08:00:00'));

        $this->assertSame('disahkan', $realization->status);
        $this->assertSame(1, DB::table('approval_history')
            ->where('realization_id', $realization->id)
            ->where('to_status', 'disahkan')
            ->count());
    }

    // --- AC1: hanya disahkan yang resmi -------------------------------------

    public function test_only_disahkan_status_is_official(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();

        $statuses = [
            'draft', 'diajukan', 'divalidasi_kasubbid', 'divalidasi_kabid',
            'direkap_sekretaris', 'dikembalikan', 'disahkan',
        ];

        foreach ($statuses as $i => $status) {
            $target = $this->makeDashTarget($iv, $doc, 'P'.$i);
            $this->makeDashRealization($target, $status);
        }

        $rows = $this->official()->builder()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('disahkan', $rows->first()->status);
        $this->assertSame('P6', $rows->first()->period_label);
    }

    // --- AC2: pemenang per kunci --------------------------------------------

    public function test_latest_finalization_wins_and_history_beats_updated_at(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $target = $this->makeDashTarget($iv, $this->makeDashPlanningDocument());

        // Disahkan lebih dulu tetapi updated_at paling baru: riwayat harus menang atas updated_at.
        $this->makeDashOfficial($target, Carbon::parse('2026-01-10 08:00:00'), null, [
            'updated_at' => Carbon::parse('2026-12-31 00:00:00'),
        ]);
        $newer = $this->makeDashOfficial($target, Carbon::parse('2026-02-10 08:00:00'));

        $rows = $this->official()->builder()->get();

        $this->assertCount(1, $rows);
        $this->assertSame($newer->id, (int) $rows->first()->realization_id);
    }

    public function test_falls_back_to_updated_at_when_no_history(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $target = $this->makeDashTarget($iv, $this->makeDashPlanningDocument());

        $this->makeDashOfficial($target, null, null, ['updated_at' => Carbon::parse('2026-01-01 00:00:00')]);
        $newer = $this->makeDashOfficial($target, null, null, ['updated_at' => Carbon::parse('2026-03-01 00:00:00')]);

        $rows = $this->official()->builder()->get();

        $this->assertCount(1, $rows);
        $this->assertSame($newer->id, (int) $rows->first()->realization_id);
    }

    public function test_tie_is_broken_by_highest_id(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $target = $this->makeDashTarget($iv, $this->makeDashPlanningDocument());
        $at = Carbon::parse('2026-02-10 08:00:00');

        $first = $this->makeDashOfficial($target, $at);
        $second = $this->makeDashOfficial($target, $at);

        $this->assertGreaterThan($first->id, $second->id);

        $rows = $this->official()->builder()->get();

        $this->assertCount(1, $rows);
        $this->assertSame($second->id, (int) $rows->first()->realization_id);
    }

    public function test_newer_revision_disahkan_replaces_older_revision(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();
        $rev1 = $this->makeDashTarget($iv, $doc, 'TW I', 1, false);
        $rev2 = $this->makeDashTarget($iv, $doc, 'TW I', 2, true);

        $this->makeDashOfficial($rev1, Carbon::parse('2026-01-10 08:00:00'));
        $latest = $this->makeDashOfficial($rev2, Carbon::parse('2026-02-10 08:00:00'));

        $rows = $this->official()->builder()->get();

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($latest->id, (int) $row->realization_id);
        $this->assertSame(2, (int) $row->measured_against_revision_no);
        $this->assertSame(0, (int) $row->target_superseded);
    }

    public function test_older_revision_stays_official_and_is_flagged_superseded(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();
        $rev1 = $this->makeDashTarget($iv, $doc, 'TW I', 1, false);
        $rev2 = $this->makeDashTarget($iv, $doc, 'TW I', 2, true);

        $official = $this->makeDashOfficial($rev1, Carbon::parse('2026-01-10 08:00:00'));
        $this->makeDashRealization($rev2, 'draft');

        $rows = $this->official()->builder()->get();

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($official->id, (int) $row->realization_id);
        $this->assertSame(1, (int) $row->measured_against_revision_no);
        $this->assertSame(2, (int) $row->anchor_revision_no);
        $this->assertSame(1, (int) $row->anchor_has_active_target);
        $this->assertSame(1, (int) $row->target_superseded);
    }

    public function test_null_achievement_is_preserved_not_coerced_to_zero(): void
    {
        $iv = $this->makeDashIndicatorVersion('nilai_langsung');
        $target = $this->makeDashTarget($iv, $this->makeDashPlanningDocument());

        $this->makeDashOfficial($target, Carbon::parse('2026-01-10 08:00:00'), null, [
            'achievement_pct' => null,
        ]);

        $row = $this->official()->builder()->get()->first();

        $this->assertNull($row->achievement_pct);
        $this->assertSame('nilai_langsung', $row->formula_type);
    }

    // --- AC3: target jangkar -------------------------------------------------

    public function test_anchor_falls_back_to_highest_revision_when_no_active_target(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();
        $rev1 = $this->makeDashTarget($iv, $doc, 'TW I', 1, false);
        $this->makeDashTarget($iv, $doc, 'TW I', 2, false);

        $this->makeDashOfficial($rev1, Carbon::parse('2026-01-10 08:00:00'));

        $row = $this->official()->builder()->get()->first();

        $this->assertSame(2, (int) $row->anchor_revision_no);
        $this->assertSame(0, (int) $row->anchor_has_active_target);
        $this->assertSame(1, (int) $row->target_superseded);
    }

    public function test_planning_document_filter_follows_the_anchor_target(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $docA = $this->makeDashPlanningDocument(2026);
        $docB = $this->makeDashPlanningDocument(2027);

        $rev1 = $this->makeDashTarget($iv, $docA, 'TW I', 1, false);
        $rev2 = $this->makeDashTarget($iv, $docB, 'TW I', 2, true);

        $official = $this->makeDashOfficial($rev1, Carbon::parse('2026-01-10 08:00:00'));
        $this->makeDashRealization($rev2, 'draft');

        $this->assertCount(0, $this->official()
            ->builder(['planning_document_id' => $docA->id])->get());

        $rows = $this->official()->builder(['planning_document_id' => $docB->id])->get();
        $this->assertCount(1, $rows);
        $this->assertSame($official->id, (int) $rows->first()->realization_id);
    }

    public function test_key_attribute_filters_narrow_the_result(): void
    {
        $doc = $this->makeDashPlanningDocument();
        $iv1 = $this->makeDashIndicatorVersion();
        $iv2 = $this->makeDashIndicatorVersion();

        $this->makeDashOfficial($this->makeDashTarget($iv1, $doc, 'P1'), Carbon::parse('2026-01-10 08:00:00'));
        $this->makeDashOfficial($this->makeDashTarget($iv2, $doc, 'P2'), Carbon::parse('2026-01-11 08:00:00'));

        $byIndicator = $this->official()->builder(['indicator_id' => $iv1->indicator_id])->get();
        $this->assertCount(1, $byIndicator);
        $this->assertSame('P1', $byIndicator->first()->period_label);

        $byPeriod = $this->official()->builder(['period_label' => 'P2'])->get();
        $this->assertCount(1, $byPeriod);
        $this->assertSame($iv2->id, (int) $byPeriod->first()->indicator_version_id);

        $byReporting = $this->official()->builder(['reporting_period_id' => $iv1->reporting_period_id])->get();
        $this->assertCount(1, $byReporting);
        $this->assertSame('P1', $byReporting->first()->period_label);
    }

    public function test_unknown_filter_is_rejected_not_silently_ignored(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->official()->builder(['bukan_filter' => 1]);
    }

    // --- AC4: peringkat global sebelum scope --------------------------------

    public function test_scope_is_applied_after_global_ranking(): void
    {
        $opA = $this->createUserWithRole('operator');
        $opB = $this->createUserWithRole('operator');
        $target = $this->makeDashTarget($this->makeDashIndicatorVersion(), $this->makeDashPlanningDocument());

        $this->makeDashOfficial($target, Carbon::parse('2026-01-10 08:00:00'), $opA);
        $replacement = $this->makeDashOfficial($target, Carbon::parse('2026-02-10 08:00:00'), $opB);

        $scopes = app(DashboardScopeService::class);

        $scopeA = $scopes->resolve($opA);
        $rowsA = $scopes->applyReadScope($this->official()->builder(), $scopeA, $opA)->get();
        $this->assertCount(0, $rowsA, 'Realisasi A sudah digantikan; tidak boleh tampil sebagai resmi.');

        $scopeB = $scopes->resolve($opB);
        $rowsB = $scopes->applyReadScope($this->official()->builder(), $scopeB, $opB)->get();
        $this->assertCount(1, $rowsB);
        $this->assertSame($replacement->id, (int) $rowsB->first()->realization_id);

        $kadis = $this->createUserWithRole('kepala_dinas');
        $rowsAll = $scopes->applyReadScope($this->official()->builder(), $scopes->resolve($kadis), $kadis)->get();
        $this->assertCount(1, $rowsAll);
        $this->assertSame($replacement->id, (int) $rowsAll->first()->realization_id);
    }

    // --- AC6: jumlah query konstan ------------------------------------------

    public function test_query_count_is_constant_regardless_of_data_size(): void
    {
        $iv = $this->makeDashIndicatorVersion();
        $doc = $this->makeDashPlanningDocument();
        $operator = $this->createUserWithRole('operator');
        $scopes = app(DashboardScopeService::class);

        $this->makeDashOfficial($this->makeDashTarget($iv, $doc, 'P0'), Carbon::parse('2026-01-10 08:00:00'), $operator);

        $measure = function () use ($scopes, $operator): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $scopes->applyReadScope($this->official()->builder(), $scopes->resolve($operator), $operator)->get();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $measure(); // pemanasan cache permission
        $small = $measure();

        for ($i = 1; $i <= 6; $i++) {
            $this->makeDashOfficial(
                $this->makeDashTarget($iv, $doc, 'P'.$i),
                Carbon::parse('2026-01-10 08:00:00')->addDays($i),
                $operator,
            );
        }

        $large = $measure();

        $this->assertSame($small, $large);
        $this->assertSame(1, $large);
    }
}