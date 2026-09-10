<?php

namespace Tests\Feature\Api\V1\MasterData;

use App\Models\Formula;
use App\Models\ReportingPeriod;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportingPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function userWithOperasionalPermission(): User
    {
        $user = User::factory()->create();
        $permission = Permission::where('name', 'master-data-operasional.manage')
            ->where('guard_name', 'web')->firstOrFail();
        $user->givePermissionTo($permission);

        return $user;
    }

    public function test_akses_ditolak_tanpa_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/master-data/reporting-periods')->assertStatus(403);
    }

    public function test_index_dan_show_berhasil(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        $periode = ReportingPeriod::create(['name' => 'Triwulanan', 'periods_per_year' => 4]);

        $this->getJson('/api/v1/master-data/reporting-periods')
            ->assertStatus(200)->assertJsonPath('success', true);

        $this->getJson("/api/v1/master-data/reporting-periods/{$periode->id}")
            ->assertStatus(200)->assertJsonPath('data.name', 'Triwulanan');
    }

    public function test_store_berhasil(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/master-data/reporting-periods', [
            'name' => 'Semesteran',
            'periods_per_year' => 2,
        ])->assertStatus(201)->assertJsonPath('data.name', 'Semesteran');
    }

    public function test_store_gagal_periods_per_year_melebihi_batas(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/master-data/reporting-periods', [
            'name' => 'Periode Tidak Valid',
            'periods_per_year' => 256,
        ])->assertStatus(422);
    }

    public function test_delete_berhasil_jika_tidak_digunakan(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        $periode = ReportingPeriod::create(['name' => 'Bulanan Tidak Terpakai', 'periods_per_year' => 12]);

        $this->deleteJson("/api/v1/master-data/reporting-periods/{$periode->id}")
            ->assertStatus(200);
    }

    public function test_delete_ditolak_jika_masih_digunakan(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        $satuan = UnitOfMeasure::create(['name' => 'Satuan Y']);
        $formula = Formula::create(['name' => 'Formula Y', 'formula_type' => 'simple']);
        $periode = ReportingPeriod::create(['name' => 'Tahunan Terpakai', 'periods_per_year' => 1]);

        $this->createIndicatorVersionUsing($satuan->id, $formula->id, $periode->id);

        $this->deleteJson("/api/v1/master-data/reporting-periods/{$periode->id}")
            ->assertStatus(409);
    }

    private function createIndicatorVersionUsing(int $unitOfMeasureId, int $formulaId, int $reportingPeriodId): void
    {
        $structureId = DB::table('performance_structure')->insertGetId([
            'level_type' => 'tujuan',
            'name' => 'Test Struktur',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = DB::table('indicators')->insertGetId([
            'structure_id' => $structureId,
            'name' => 'Test Indikator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('indicator_versions')->insert([
            'indicator_id' => $indicatorId,
            'unit_of_measure_id' => $unitOfMeasureId,
            'formula_id' => $formulaId,
            'reporting_period_id' => $reportingPeriodId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}