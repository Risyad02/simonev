<?php

namespace Tests\Feature\Api\V1\Target;

use App\Models\Formula;
use App\Models\Indicator;
use App\Models\IndicatorVersion;
use App\Models\PlanningDocument;
use App\Models\ReportingPeriod;
use App\Models\Target;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TargetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsViewer(string $role = 'kepala_dinas'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function unitOfMeasure(): UnitOfMeasure
    {
        return UnitOfMeasure::create(['name' => 'Persen', 'symbol' => '%']);
    }

    private function formula(): Formula
    {
        return Formula::create([
            'name' => 'Persentase Capaian',
            'formula_type' => 'persentase_capaian',
            'description' => 'Realisasi dibagi Target dikali 100',
        ]);
    }

    private function reportingPeriod(): ReportingPeriod
    {
        return ReportingPeriod::create(['name' => 'Bulanan', 'periods_per_year' => 12]);
    }

    private function createIndicatorVersion(): IndicatorVersion
    {
        $indicator = Indicator::factory()->create();

        return IndicatorVersion::create([
            'indicator_id' => $indicator->id,
            'unit_of_measure_id' => $this->unitOfMeasure()->id,
            'formula_id' => $this->formula()->id,
            'reporting_period_id' => $this->reportingPeriod()->id,
            'valid_from' => now(),
            'is_active' => true,
        ]);
    }

    // --- RBAC ---

    public function test_akses_ditolak_tanpa_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('publik');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/targets')->assertStatus(403);
    }

    public function test_admin_dapat_membuat_target_awal(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $this->postJson('/api/v1/targets', [
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'target_value' => 100,
        ])->assertStatus(201)->assertJsonPath('data.revision_no', 1);

        $this->assertDatabaseHas('targets', [
            'indicator_version_id' => $iv->id,
            'revision_no' => 1,
            'is_active' => true,
        ]);
    }

    public function test_operator_tidak_dapat_membuat_target(): void
    {
        $this->actingAsViewer('operator');

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $this->postJson('/api/v1/targets', [
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'target_value' => 100,
        ])->assertStatus(403);
    }

    public function test_target_discuss_tidak_memperoleh_akses_mutation(): void
    {
        $this->actingAsViewer('kepala_bidang');

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $this->postJson('/api/v1/targets', [
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'target_value' => 100,
        ])->assertStatus(403);
    }

    // --- Creation ---

    public function test_reason_boleh_null_untuk_target_awal(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $this->postJson('/api/v1/targets', [
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'target_value' => 100,
        ])->assertStatus(201)->assertJsonPath('data.reason', null);
    }

    public function test_created_by_berasal_dari_actor(): void
    {
        $actor = $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $this->postJson('/api/v1/targets', [
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'target_value' => 100,
        ])->assertStatus(201)->assertJsonPath('data.created_by', $actor->id);
    }

    // --- Active invariant ---

    public function test_target_awal_kedua_dengan_kombinasi_sama_ditolak(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/targets', [
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'target_value' => 100,
        ])->assertStatus(409);
    }

    // --- Revision ---

    public function test_admin_dapat_membuat_revisi(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $target = Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/targets/{$target->id}/revisions", [
            'target_value' => 150,
            'reason' => 'Penyesuaian RKPD Perubahan',
        ])->assertStatus(201)->assertJsonPath('data.revision_no', 2);
    }

    public function test_revision_menghasilkan_row_baru_dan_menonaktifkan_lama(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $target = Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/targets/{$target->id}/revisions", [
            'target_value' => 150,
            'reason' => 'Penyesuaian RKPD Perubahan',
        ])->assertStatus(201);

        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('targets', [
            'indicator_version_id' => $iv->id,
            'period_label' => 'Triwulan I 2026',
            'revision_no' => 2,
            'is_active' => true,
        ]);
    }

    public function test_old_target_mendapatkan_valid_to_new_target_valid_to_null(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $target = Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'is_active' => true,
            'valid_from' => now(),
            'valid_to' => null,
        ]);

        $response = $this->postJson("/api/v1/targets/{$target->id}/revisions", [
            'target_value' => 150,
            'reason' => 'Penyesuaian RKPD Perubahan',
        ])->assertStatus(201);

        $this->assertNotNull($target->fresh()->valid_to);
        $this->assertNull($response->json('data.valid_to'));
        $this->assertNotNull($response->json('data.valid_from'));
    }

    public function test_reason_wajib_pada_revisi(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $target = Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/targets/{$target->id}/revisions", [
            'target_value' => 150,
        ])->assertStatus(422);
    }

    public function test_planning_document_inherited_jika_tidak_diberikan(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $target = Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/targets/{$target->id}/revisions", [
            'target_value' => 150,
            'reason' => 'Penyesuaian',
        ])->assertStatus(201)->assertJsonPath('data.planning_document_id', $pd->id);
    }

    public function test_planning_document_berubah_jika_diberikan_eksplisit(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pdLama = PlanningDocument::factory()->create();
        $pdBaru = PlanningDocument::factory()->create();

        $target = Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pdLama->id,
            'period_label' => 'Triwulan I 2026',
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/targets/{$target->id}/revisions", [
            'target_value' => 150,
            'reason' => 'Perubahan dokumen acuan',
            'planning_document_id' => $pdBaru->id,
        ])->assertStatus(201)->assertJsonPath('data.planning_document_id', $pdBaru->id);

        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'planning_document_id' => $pdLama->id,
        ]);
    }

    public function test_revisi_dari_target_nonaktif_ditolak(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $target = Target::factory()->inactive()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
        ]);

        $this->postJson("/api/v1/targets/{$target->id}/revisions", [
            'target_value' => 150,
            'reason' => 'Percobaan revisi tidak valid',
        ])->assertStatus(409);
    }

    // --- History ---

    public function test_riwayat_revisi_menampilkan_seluruh_versi(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $target = Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/targets/{$target->id}/revisions", [
            'target_value' => 150,
            'reason' => 'Revisi pertama',
        ])->assertStatus(201);

        $this->actingAsViewer('kepala_dinas');

        $this->getJson("/api/v1/targets/{$target->id}/revisions")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // --- Realization history ---

    public function test_realization_lama_tetap_menunjuk_target_lama_setelah_revisi(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $target = Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'is_active' => true,
        ]);

        DB::table('realizations')->insert([
            'target_id' => $target->id,
            'realization_value' => 80,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/v1/targets/{$target->id}/revisions", [
            'target_value' => 150,
            'reason' => 'Revisi setelah realisasi tercatat',
        ])->assertStatus(201);

        $this->assertDatabaseHas('realizations', [
            'target_id' => $target->id,
        ]);
    }

    // --- Delete guard ---

    public function test_delete_berhasil_jika_tidak_ada_realization(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $target = Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
        ]);

        $this->deleteJson("/api/v1/targets/{$target->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
    }

    public function test_delete_ditolak_jika_ada_realization(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $target = Target::factory()->create([
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
        ]);

        DB::table('realizations')->insert([
            'target_id' => $target->id,
            'realization_value' => 80,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/api/v1/targets/{$target->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('realizations', ['target_id' => $target->id]);
    }

    // --- Validation ---

    public function test_store_gagal_indicator_version_id_tidak_ada(): void
    {
        $this->actingAsAdmin();

        $pd = PlanningDocument::factory()->create();

        $this->postJson('/api/v1/targets', [
            'indicator_version_id' => 99999,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'target_value' => 100,
        ])->assertStatus(422);
    }

    public function test_store_gagal_planning_document_id_tidak_ada(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();

        $this->postJson('/api/v1/targets', [
            'indicator_version_id' => $iv->id,
            'planning_document_id' => 99999,
            'period_label' => 'Triwulan I 2026',
            'target_value' => 100,
        ])->assertStatus(422);
    }

    public function test_store_gagal_target_value_bukan_angka(): void
    {
        $this->actingAsAdmin();

        $iv = $this->createIndicatorVersion();
        $pd = PlanningDocument::factory()->create();

        $this->postJson('/api/v1/targets', [
            'indicator_version_id' => $iv->id,
            'planning_document_id' => $pd->id,
            'period_label' => 'Triwulan I 2026',
            'target_value' => 'bukan-angka',
        ])->assertStatus(422);
    }
}