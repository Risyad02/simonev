<?php

namespace Tests\Feature\Api\V1\Realization;

use App\Models\Formula;
use App\Models\IndicatorVersion;
use App\Models\ReportingPeriod;
use App\Models\Target;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\PlanningDocument;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function actingAsOperator(): User
    {
        $user = User::factory()->create();
        $user->assignRole('operator');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsKepalaBidang(): User
    {
        $user = User::factory()->create();
        $user->assignRole('kepala_bidang');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsSekretaris(): User
    {
        $user = User::factory()->create();
        $user->assignRole('sekretaris');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * Membuat Target aktif dengan formula persentase_capaian (system) —
     * mengembalikan Target agar bisa dipakai sebagai target_id di test.
     */
    private function createActiveTargetWithFormula(string $formulaType = 'persentase_capaian'): Target
    {
        $satuan = UnitOfMeasure::create(['name' => 'Satuan Realisasi Test '.uniqid()]);
        $formula = Formula::create([
            'name' => 'Formula Realisasi Test '.uniqid(),
            'formula_type' => $formulaType,
            'type' => 'system',
        ]);
        $periode = ReportingPeriod::create(['name' => 'Periode Realisasi Test '.uniqid(), 'periods_per_year' => 12]);

        $planningDocument = PlanningDocument::create([
            'document_type' => 'Renstra',
            'year' => 2026,
            'period_start_year' => 2026,
            'period_end_year' => 2030,
            'version_no' => 1,
            'status' => 'active',
        ]);

        $structureId = DB::table('performance_structure')->insertGetId([
            'level_type' => 'tujuan',
            'name' => 'Struktur Realisasi Test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = DB::table('indicators')->insertGetId([
            'structure_id' => $structureId,
            'name' => 'Indikator Realisasi Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorVersion = IndicatorVersion::create([
            'indicator_id' => $indicatorId,
            'unit_of_measure_id' => $satuan->id,
            'formula_id' => $formula->id,
            'reporting_period_id' => $periode->id,
            'is_active' => true,
            'valid_from' => now(),
        ]);

        return Target::create([
            'indicator_version_id' => $indicatorVersion->id,
            'planning_document_id' => $planningDocument->id,
            'period_label' => 'Tahun Test',
            'target_value' => 100,
            'revision_no' => 1,
            'is_active' => true,
            'valid_from' => now(),
            'created_by' => null,
        ]);
    }

    // ── STORE ──────────────────────────────────────────────────────────

    public function test_operator_dapat_membuat_realisasi_dengan_formula_persentase_capaian(): void
    {
        $this->actingAsOperator();
        $target = $this->createActiveTargetWithFormula();

        $response = $this->postJson('/api/v1/realizations', [
            'target_id' => $target->id,
            'realization_value' => 80,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.target_id', $target->id)
            ->assertJsonPath('data.status', 'draft');

        $this->assertEquals(80.0, (float) $response->json('data.achievement_pct'));
        $this->assertEquals(-20.0, (float) $response->json('data.deviation'));

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Realization',
            'action' => 'realization_input',
        ]);
    }

    public function test_operator_tanpa_permission_manage_ditolak(): void
    {
        $user = User::factory()->create();
        $user->assignRole('kepala_bidang');
        Sanctum::actingAs($user, ['*']);

        $target = $this->createActiveTargetWithFormula();

        $this->postJson('/api/v1/realizations', [
            'target_id' => $target->id,
            'realization_value' => 80,
        ])->assertStatus(403);
    }

    public function test_admin_backup_input_tercatat_di_audit_trail_dengan_action_berbeda(): void
    {
        $this->actingAsAdmin();
        $target = $this->createActiveTargetWithFormula();

        $this->postJson('/api/v1/realizations', [
            'target_id' => $target->id,
            'realization_value' => 90,
        ])->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Realization',
            'action' => 'backup_operator_input',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'entity_type' => 'Realization',
            'action' => 'realization_input',
        ]);
    }

    public function test_realisasi_ditolak_pada_target_nonaktif(): void
    {
        $this->actingAsOperator();
        $target = $this->createActiveTargetWithFormula();
        $target->update(['is_active' => false]);

        $this->postJson('/api/v1/realizations', [
            'target_id' => $target->id,
            'realization_value' => 80,
        ])->assertStatus(409);
    }

    public function test_realisasi_ditolak_target_id_tidak_ada(): void
    {
        $this->actingAsOperator();

        $this->postJson('/api/v1/realizations', [
            'target_id' => 99999,
            'realization_value' => 80,
        ])->assertStatus(422);
    }

    public function test_realisasi_ditolak_realization_value_bukan_angka(): void
    {
        $this->actingAsOperator();
        $target = $this->createActiveTargetWithFormula();

        $this->postJson('/api/v1/realizations', [
            'target_id' => $target->id,
            'realization_value' => 'bukan-angka',
        ])->assertStatus(422);
    }

    public function test_formula_target_per_realisasi_dihitung_benar(): void
    {
        $this->actingAsOperator();
        $target = $this->createActiveTargetWithFormula('target_per_realisasi');

        $response = $this->postJson('/api/v1/realizations', [
            'target_id' => $target->id,
            'realization_value' => 50,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(200.0, (float) $response->json('data.achievement_pct'));
    }

    public function test_formula_nilai_langsung_menghasilkan_achievement_dan_deviation_null(): void
    {
        $this->actingAsOperator();
        $target = $this->createActiveTargetWithFormula('nilai_langsung');

        $response = $this->postJson('/api/v1/realizations', [
            'target_id' => $target->id,
            'realization_value' => 80,
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('data.achievement_pct'));
        $this->assertNull($response->json('data.deviation'));
    }

    public function test_division_by_zero_dikembalikan_sebagai_422_bukan_500(): void
    {
        $this->actingAsOperator();
        $target = $this->createActiveTargetWithFormula('persentase_capaian');
        $target->update(['target_value' => 0]);

        $this->postJson('/api/v1/realizations', [
            'target_id' => $target->id,
            'realization_value' => 80,
        ])->assertStatus(422);
    }

    public function test_input_by_achievement_pct_dan_status_tidak_bisa_dioverride_dari_request(): void
    {
        $actor = $this->actingAsOperator();
        $target = $this->createActiveTargetWithFormula();
        $otherUser = User::factory()->create();

        $response = $this->postJson('/api/v1/realizations', [
            'target_id' => $target->id,
            'realization_value' => 80,
            'input_by' => $otherUser->id,
            'achievement_pct' => 999,
            'deviation' => 999,
            'status' => 'disahkan',
        ]);

        $response->assertStatus(201);
        $this->assertEquals($actor->id, $response->json('data.input_by'));
        $this->assertEquals(80.0, (float) $response->json('data.achievement_pct'));
        $this->assertEquals('draft', $response->json('data.status'));
    }

    // ── INDEX / OWNERSHIP SCOPE ──────────────────────────────────────────

    public function test_operator_hanya_melihat_realisasi_miliknya_sendiri(): void
    {
        $target = $this->createActiveTargetWithFormula();

        $operatorA = $this->actingAsOperator();
        $this->postJson('/api/v1/realizations', ['target_id' => $target->id, 'realization_value' => 10]);

        $operatorB = $this->actingAsOperator();
        $this->postJson('/api/v1/realizations', ['target_id' => $target->id, 'realization_value' => 20]);

        Sanctum::actingAs($operatorA, ['*']);
        $response = $this->getJson('/api/v1/realizations');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($operatorA->id, $data[0]['input_by']);
    }

    public function test_sekretaris_melihat_semua_realisasi_cross_unit(): void
    {
        $target = $this->createActiveTargetWithFormula();

        $operator = $this->actingAsOperator();
        $this->postJson('/api/v1/realizations', ['target_id' => $target->id, 'realization_value' => 10]);

        $this->actingAsSekretaris();
        $response = $this->getJson('/api/v1/realizations');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_kepala_bidang_ditolak_403_karena_unit_scope_belum_tersedia(): void
    {
        $this->actingAsKepalaBidang();

        $this->getJson('/api/v1/realizations')->assertStatus(403);
    }

    // ── SHOW / IDOR ──────────────────────────────────────────────────────

    public function test_operator_tidak_bisa_lihat_realisasi_milik_operator_lain(): void
    {
        $target = $this->createActiveTargetWithFormula();

        $this->actingAsOperator();
        $created = $this->postJson('/api/v1/realizations', ['target_id' => $target->id, 'realization_value' => 10]);
        $realizationId = $created->json('data.id');

        $this->actingAsOperator(); // operator lain
        $this->getJson("/api/v1/realizations/{$realizationId}")->assertStatus(403);
    }

    public function test_sekretaris_bisa_lihat_detail_realisasi_siapapun(): void
    {
        $target = $this->createActiveTargetWithFormula();

        $this->actingAsOperator();
        $created = $this->postJson('/api/v1/realizations', ['target_id' => $target->id, 'realization_value' => 10]);
        $realizationId = $created->json('data.id');

        $this->actingAsSekretaris();
        $this->getJson("/api/v1/realizations/{$realizationId}")->assertStatus(200);
    }
}