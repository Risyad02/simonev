<?php

namespace Tests\Feature\Api\V1\Indicator;

use App\Models\Formula;
use App\Models\Indicator;
use App\Models\PerformanceStructure;
use App\Models\ReportingPeriod;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IndicatorTest extends TestCase
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

    private function actingAsViewer(string $role = 'operator'): User
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

    // --- RBAC ---

    public function test_akses_ditolak_tanpa_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('publik');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/indicators')->assertStatus(403);
    }

    public function test_operator_dapat_melihat_daftar_indikator(): void
    {
        $this->actingAsViewer('operator');

        Indicator::factory()->create(['name' => 'Indikator A']);

        $this->getJson('/api/v1/indicators')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_operator_tidak_dapat_membuat_indikator(): void
    {
        $this->actingAsViewer('operator');

        $structure = PerformanceStructure::factory()->create();

        $this->postJson('/api/v1/indicators', [
            'structure_id' => $structure->id,
            'name' => 'Indikator Baru',
        ])->assertStatus(403);
    }

    public function test_kepala_bidang_tidak_dapat_mengakses_indikator(): void
    {
        $this->actingAsViewer('kepala_bidang');

        $this->getJson('/api/v1/indicators')->assertStatus(403);
    }

    public function test_admin_dapat_membuat_indikator(): void
    {
        $this->actingAsAdmin();

        $structure = PerformanceStructure::factory()->create();

        $this->postJson('/api/v1/indicators', [
            'structure_id' => $structure->id,
            'name' => 'Indikator Baru',
        ])->assertStatus(201)->assertJsonPath('data.name', 'Indikator Baru');

        $this->assertDatabaseHas('indicators', ['name' => 'Indikator Baru']);
    }

    // --- CRUD Indicator ---

    public function test_store_gagal_structure_id_tidak_ada(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/indicators', [
            'structure_id' => 99999,
            'name' => 'Indikator X',
        ])->assertStatus(422);
    }

    public function test_show_berhasil(): void
    {
        $this->actingAsViewer('sekretaris');

        $item = Indicator::factory()->create(['name' => 'Indikator Uji']);

        $this->getJson("/api/v1/indicators/{$item->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Indikator Uji');
    }

    public function test_update_berhasil(): void
    {
        $this->actingAsAdmin();

        $item = Indicator::factory()->create(['name' => 'Nama Lama']);

        $this->putJson("/api/v1/indicators/{$item->id}", [
            'name' => 'Nama Baru',
        ])->assertStatus(200)->assertJsonPath('data.name', 'Nama Baru');
    }

    // --- Delete guard ---

    public function test_delete_berhasil_jika_belum_punya_versi(): void
    {
        $this->actingAsAdmin();

        $item = Indicator::factory()->create();

        $this->deleteJson("/api/v1/indicators/{$item->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('indicators', ['id' => $item->id]);
    }

    public function test_delete_ditolak_jika_sudah_punya_versi(): void
    {
        $this->actingAsAdmin();

        $item = Indicator::factory()->create();

        $item->versions()->create([
            'unit_of_measure_id' => $this->unitOfMeasure()->id,
            'formula_id' => $this->formula()->id,
            'reporting_period_id' => $this->reportingPeriod()->id,
            'valid_from' => now(),
            'is_active' => true,
        ]);

        $this->deleteJson("/api/v1/indicators/{$item->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('indicators', ['id' => $item->id]);
    }

    // --- Indicator Version ---

    public function test_admin_dapat_membuat_versi_pertama(): void
    {
        $this->actingAsAdmin();

        $indicator = Indicator::factory()->create();
        $uom = $this->unitOfMeasure();
        $formula = $this->formula();
        $period = $this->reportingPeriod();

        $this->postJson("/api/v1/indicators/{$indicator->id}/versions", [
            'unit_of_measure_id' => $uom->id,
            'formula_id' => $formula->id,
            'reporting_period_id' => $period->id,
        ])->assertStatus(201)->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('indicator_versions', [
            'indicator_id' => $indicator->id,
            'is_active' => true,
        ]);
    }

    public function test_versi_baru_menonaktifkan_versi_lama(): void
    {
        $this->actingAsAdmin();

        $indicator = Indicator::factory()->create();
        $uom = $this->unitOfMeasure();
        $formula = $this->formula();
        $period = $this->reportingPeriod();

        $v1 = $this->postJson("/api/v1/indicators/{$indicator->id}/versions", [
            'unit_of_measure_id' => $uom->id,
            'formula_id' => $formula->id,
            'reporting_period_id' => $period->id,
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/indicators/{$indicator->id}/versions", [
            'unit_of_measure_id' => $uom->id,
            'formula_id' => $formula->id,
            'reporting_period_id' => $period->id,
            'data_source' => 'Revisi kedua',
        ])->assertStatus(201);

        $this->assertDatabaseHas('indicator_versions', [
            'id' => $v1['id'],
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('indicator_versions', [
            'indicator_id' => $indicator->id,
            'data_source' => 'Revisi kedua',
            'is_active' => true,
        ]);
    }

    public function test_endpoint_active_mengembalikan_versi_terbaru(): void
    {
        $this->actingAsViewer('operator');

        $indicator = Indicator::factory()->create();
        $uom = $this->unitOfMeasure();
        $formula = $this->formula();
        $period = $this->reportingPeriod();

        $indicator->versions()->create([
            'unit_of_measure_id' => $uom->id,
            'formula_id' => $formula->id,
            'reporting_period_id' => $period->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now(),
            'is_active' => false,
        ]);

        $active = $indicator->versions()->create([
            'unit_of_measure_id' => $uom->id,
            'formula_id' => $formula->id,
            'reporting_period_id' => $period->id,
            'valid_from' => now(),
            'is_active' => true,
        ]);

        $this->getJson("/api/v1/indicators/{$indicator->id}/versions/active")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $active->id);
    }

    public function test_operator_tidak_dapat_membuat_versi(): void
    {
        $this->actingAsViewer('operator');

        $indicator = Indicator::factory()->create();
        $uom = $this->unitOfMeasure();
        $formula = $this->formula();
        $period = $this->reportingPeriod();

        $this->postJson("/api/v1/indicators/{$indicator->id}/versions", [
            'unit_of_measure_id' => $uom->id,
            'formula_id' => $formula->id,
            'reporting_period_id' => $period->id,
        ])->assertStatus(403);
    }

    // --- Immutability confirmation ---

    public function test_tidak_ada_endpoint_update_atau_delete_versi(): void
    {
        $this->actingAsAdmin();

        $indicator = Indicator::factory()->create();
        $version = $indicator->versions()->create([
            'unit_of_measure_id' => $this->unitOfMeasure()->id,
            'formula_id' => $this->formula()->id,
            'reporting_period_id' => $this->reportingPeriod()->id,
            'valid_from' => now(),
            'is_active' => true,
        ]);

        $this->putJson("/api/v1/indicators/{$indicator->id}/versions/{$version->id}", [])
            ->assertStatus(405);

        $this->deleteJson("/api/v1/indicators/{$indicator->id}/versions/{$version->id}")
            ->assertStatus(405);
    }
}