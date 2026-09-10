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
use Tests\TestCase;

class FormulaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
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

    public function test_operator_ditolak_akses_index(): void
    {
        $user = User::factory()->create();
        $user->assignRole('operator');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/master-data/formulas')->assertStatus(403);
    }

    public function test_admin_bisa_melihat_daftar_formula(): void
    {
        $this->actingAsAdmin();

        Formula::create(['name' => 'Formula View', 'formula_type' => 'simple']);

        $this->getJson('/api/v1/master-data/formulas')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_admin_ditolak_membuat_formula(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/master-data/formulas', [
            'name' => 'Formula Baru',
            'formula_type' => 'simple',
        ])->assertStatus(403);
    }

    public function test_super_admin_berhasil_membuat_formula(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/master-data/formulas', [
            'name' => 'Formula Super Admin',
            'formula_type' => 'simple',
        ])->assertStatus(201)->assertJsonPath('data.name', 'Formula Super Admin');
    }

    public function test_super_admin_berhasil_update_formula(): void
    {
        $this->actingAsSuperAdmin();

        $formula = Formula::create(['name' => 'Formula Lama', 'formula_type' => 'simple']);

        $this->putJson("/api/v1/master-data/formulas/{$formula->id}", [
            'name' => 'Formula Baru Nama',
        ])->assertStatus(200)->assertJsonPath('data.name', 'Formula Baru Nama');
    }

    public function test_delete_berhasil_jika_tidak_digunakan(): void
    {
        $this->actingAsSuperAdmin();

        $formula = Formula::create(['name' => 'Formula Tidak Terpakai', 'formula_type' => 'simple']);

        $this->deleteJson("/api/v1/master-data/formulas/{$formula->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('formulas', ['id' => $formula->id]);
    }

    public function test_delete_ditolak_jika_masih_digunakan(): void
    {
        $this->actingAsSuperAdmin();

        $satuan = UnitOfMeasure::create(['name' => 'Satuan X']);
        $formula = Formula::create(['name' => 'Formula Terpakai', 'formula_type' => 'simple']);
        $periode = ReportingPeriod::create(['name' => 'Bulanan', 'periods_per_year' => 12]);

        $this->createIndicatorVersionUsing($satuan->id, $formula->id, $periode->id);

        $this->deleteJson("/api/v1/master-data/formulas/{$formula->id}")
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