<?php

namespace Tests\Concerns;

use App\Models\Formula;
use App\Models\IndicatorVersion;
use App\Models\PlanningDocument;
use App\Models\ReportingPeriod;
use App\Models\Target;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Fixture bersama untuk test Realization/workflow (Phase 11).
 * Pemakai harus mewarisi Tests\TestCase.
 */
trait CreatesRealizationFixtures
{
    protected function seedRolesAndPermissions(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    protected function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * Target aktif dengan formula system (default persentase_capaian).
     */
    protected function createActiveTargetWithFormula(string $formulaType = 'persentase_capaian'): Target
    {
        $satuan = UnitOfMeasure::create(['name' => 'Satuan Realisasi Test '.uniqid()]);
        $formula = Formula::create([
            'name'         => 'Formula Realisasi Test '.uniqid(),
            'formula_type' => $formulaType,
            'type'         => 'system',
        ]);
        $periode = ReportingPeriod::create(['name' => 'Periode RealisasiTest '.uniqid(), 'periods_per_year' => 12]);

        $planningDocument = PlanningDocument::create([
            'document_type'     => 'Renstra',
            'year'              => 2026,
            'period_start_year' => 2026,
            'period_end_year'   => 2030,
            'version_no'        => 1,
            'status'            => 'active',
        ]);

        $structureId = DB::table('performance_structure')->insertGetId([
            'level_type' => 'tujuan',
            'name'       => 'Struktur Realisasi Test',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = DB::table('indicators')->insertGetId([
            'structure_id' => $structureId,
            'name'         => 'Indikator Realisasi Test',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $indicatorVersion = IndicatorVersion::create([
            'indicator_id'        => $indicatorId,
            'unit_of_measure_id'  => $satuan->id,
            'formula_id'          => $formula->id,
            'reporting_period_id' => $periode->id,
            'is_active'           => true,
            'valid_from'          => now(),
        ]);

        return Target::create([
            'indicator_version_id' => $indicatorVersion->id,
            'planning_document_id' => $planningDocument->id,
            'period_label'         => 'Tahun Test',
            'target_value'         => 100,
            'revision_no'          => 1,
            'is_active'            => true,
            'valid_from'           => now(),
            'created_by'           => null,
        ]);
    }
}