<?php

namespace Tests\Feature\Api\V1\Dashboard;

use App\Models\IndicatorVersion;
use App\Models\PlanningDocument;
use App\Models\ReportingPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesDashboardFixtures;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class DashboardFilterOptionsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesRealizationFixtures;
    use CreatesDashboardFixtures;

    private const URL = '/api/v1/dashboard/filter-options';

    private PlanningDocument $docA;
    private PlanningDocument $docB;
    private IndicatorVersion $ivA;
    private IndicatorVersion $ivB;
    private int $naik;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    /**
     * ivA (arah Naik) : target docA 'TW I'  resmi, dimiliki $ownerA
     * ivB (tanpa arah): target docB 'TW II' resmi, dimiliki $ownerB
     * ivC             : target docA 'TW III' hanya draft (tidak boleh muncul)
     */
    private function seedOptions(?User $ownerA = null, ?User $ownerB = null): void
    {
        $this->docA = $this->makeDashPlanningDocument(2026);
        $this->docB = $this->makeDashPlanningDocument(2027);
        $this->naik = $this->makeDashDirection('Naik Lebih Baik');
        $at = Carbon::parse('2026-01-10 08:00:00');

        $this->ivA = $this->makeDashIndicatorVersion('persentase_capaian', $this->naik);
        $this->makeDashOfficial($this->makeDashTarget($this->ivA, $this->docA, 'TW I'), $at, $ownerA);

        $this->ivB = $this->makeDashIndicatorVersion('persentase_capaian', null);
        $this->makeDashOfficial($this->makeDashTarget($this->ivB, $this->docB, 'TW II'), $at, $ownerB);

        $ivC = $this->makeDashIndicatorVersion();
        $this->makeDashRealization($this->makeDashTarget($ivC, $this->docA, 'TW III'), 'draft');
    }

    private function indicatorEntry(IndicatorVersion $iv): array
    {
        return [
            'id'   => $iv->indicator_id,
            'name' => DB::table('indicators')->where('id', $iv->indicator_id)->value('name'),
        ];
    }

    private function reportingPeriodEntry(IndicatorVersion $iv): array
    {
        return [
            'id'   => $iv->reporting_period_id,
            'name' => ReportingPeriod::query()->whereKey($iv->reporting_period_id)->value('name'),
        ];
    }

    // --- Akses ---------------------------------------------------------------

    public function test_unauthenticated_request_gets_401(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
    }

    public function test_every_dashboard_role_can_read_and_publik_cannot(): void
    {
        foreach ([
            'super_admin', 'admin', 'operator', 'kepala_sub_bidang',
            'kepala_bidang', 'sekretaris', 'kepala_dinas', 'pimpinan',
        ] as $role) {
            Sanctum::actingAs($this->createUserWithRole($role));
            $this->getJson(self::URL)->assertOk()->assertJsonPath('success', true);
        }

        Sanctum::actingAs($this->createUserWithRole('publik'));
        $this->getJson(self::URL)->assertStatus(403);
    }

    // --- Isi -----------------------------------------------------------------

    public function test_options_contain_only_values_that_have_official_numbers(): void
    {
        $this->seedOptions();
        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $this->assertSame([
            'planning_documents' => [
                ['id' => $this->docA->id, 'document_type' => 'Renstra', 'year' => 2026],
                ['id' => $this->docB->id, 'document_type' => 'Renstra', 'year' => 2027],
            ],
            'period_labels'      => ['TW I', 'TW II'],
            'reporting_periods'  => [
                $this->reportingPeriodEntry($this->ivA),
                $this->reportingPeriodEntry($this->ivB),
            ],
            'directions'         => [['id' => $this->naik, 'name' => 'Naik Lebih Baik']],
            'indicators'         => [
                $this->indicatorEntry($this->ivA),
                $this->indicatorEntry($this->ivB),
            ],
        ], $this->getJson(self::URL)->assertOk()->json('data'));
    }

    public function test_empty_database_returns_empty_lists(): void
    {
        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $this->assertSame([
            'planning_documents' => [],
            'period_labels'      => [],
            'reporting_periods'  => [],
            'directions'         => [],
            'indicators'         => [],
        ], $this->getJson(self::URL)->assertOk()->json('data'));
    }

    // --- Scope: tidak membocorkan -------------------------------------------

    public function test_operator_only_sees_options_from_its_own_official_data(): void
    {
        $opA = $this->createUserWithRole('operator');
        $opB = $this->createUserWithRole('operator');
        $this->seedOptions($opA, $opB);

        Sanctum::actingAs($opA);
        $data = $this->getJson(self::URL)->assertOk()->json('data');

        $this->assertSame([['id' => $this->docA->id, 'document_type' => 'Renstra', 'year' => 2026]], $data['planning_documents']);
        $this->assertSame(['TW I'], $data['period_labels']);
        $this->assertSame([$this->indicatorEntry($this->ivA)], $data['indicators']);
        $this->assertSame([['id' => $this->naik, 'name' => 'Naik Lebih Baik']], $data['directions']);

        Sanctum::actingAs($opB);
        $data = $this->getJson(self::URL)->assertOk()->json('data');

        $this->assertSame(['TW II'], $data['period_labels']);
        $this->assertSame([$this->indicatorEntry($this->ivB)], $data['indicators']);
        $this->assertSame([], $data['directions']);
    }

    public function test_non_row_level_scopes_never_receive_the_indicator_list(): void
    {
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $opB = $this->createUserWithRole('operator');
        $this->seedOptions(null, $opB);

        $acted = DB::table('realizations')->where('input_by', $opB->id)->value('id');
        $this->makeDashHistory(
            \App\Models\Realization::query()->findOrFail($acted),
            'diajukan',
            'divalidasi_kasubbid',
            $kasubbid,
            Carbon::now()
        );

        Sanctum::actingAs($kasubbid);
        $data = $this->getJson(self::URL)->assertOk()->json('data');
        $this->assertNull($data['indicators']);
        $this->assertSame(['TW II'], $data['period_labels']);

        Sanctum::actingAs($this->createUserWithRole('pimpinan'));
        $data = $this->getJson(self::URL)->assertOk()->json('data');
        $this->assertNull($data['indicators']);
        $this->assertSame(['TW I', 'TW II'], $data['period_labels']);
    }

    public function test_response_carries_meta_basis(): void
    {
        Sanctum::actingAs($this->createUserWithRole('kepala_dinas'));

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('meta.basis.official_rule', 'temporary_latest_disahkan')
            ->assertJsonPath('meta.basis.scope.mode', 'full');
    }
}