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

class UnitOfMeasureTest extends TestCase
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
        $user->assignRole('operator');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/master-data/units-of-measure')->assertStatus(403);
    }

    public function test_index_berhasil_dengan_permission(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        UnitOfMeasure::create(['name' => 'Kilogram', 'symbol' => 'kg']);

        $this->getJson('/api/v1/master-data/units-of-measure')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_berhasil(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        $satuan = UnitOfMeasure::create(['name' => 'Meter', 'symbol' => 'm']);

        $this->getJson("/api/v1/master-data/units-of-measure/{$satuan->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Meter');
    }

    public function test_store_berhasil(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/master-data/units-of-measure', [
            'name' => 'Ton',
            'symbol' => 't',
        ])->assertStatus(201)->assertJsonPath('data.name', 'Ton');

        $this->assertDatabaseHas('units_of_measure', ['name' => 'Ton']);
    }

    public function test_store_gagal_duplikat_nama(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        UnitOfMeasure::create(['name' => 'Liter']);

        $this->postJson('/api/v1/master-data/units-of-measure', [
            'name' => 'Liter',
        ])->assertStatus(422);
    }

    public function test_update_berhasil(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        $satuan = UnitOfMeasure::create(['name' => 'Gram']);

        $this->putJson("/api/v1/master-data/units-of-measure/{$satuan->id}", [
            'name' => 'Gram (g)',
        ])->assertStatus(200)->assertJsonPath('data.name', 'Gram (g)');
    }

    public function test_delete_berhasil_jika_tidak_digunakan(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        $satuan = UnitOfMeasure::create(['name' => 'Persen', 'symbol' => '%']);

        $this->deleteJson("/api/v1/master-data/units-of-measure/{$satuan->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('units_of_measure', ['id' => $satuan->id]);
    }

    public function test_delete_ditolak_jika_masih_digunakan(): void
    {
        $user = $this->userWithOperasionalPermission();
        Sanctum::actingAs($user, ['*']);

        $satuan = UnitOfMeasure::create(['name' => 'Unit Terpakai']);
        $formula = Formula::create(['name' => 'Formula A', 'formula_type' => 'simple']);
        $periode = ReportingPeriod::create(['name' => 'Tahunan', 'periods_per_year' => 1]);

        $this->createIndicatorVersionUsing($satuan->id, $formula->id, $periode->id);

        $this->deleteJson("/api/v1/master-data/units-of-measure/{$satuan->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('units_of_measure', ['id' => $satuan->id]);
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