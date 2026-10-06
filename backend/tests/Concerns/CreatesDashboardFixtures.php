<?php

namespace Tests\Concerns;

use App\Models\ApprovalHistory;
use App\Models\Formula;
use App\Models\IndicatorVersion;
use App\Models\PlanningDocument;
use App\Models\Realization;
use App\Models\ReportingPeriod;
use App\Models\Target;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fixture dashboard (Phase 12A). Berdiri sendiri; dipakai bersama
 * CreatesRealizationFixtures (seedRolesAndPermissions, createUserWithRole).
 * Seluruh method berawalan makeDash agar tidak bentrok dengan trait lain.
 */
trait CreatesDashboardFixtures
{
    protected function makeDashPlanningDocument(int $year = 2026): PlanningDocument
    {
        return PlanningDocument::create([
            'document_type'     => 'Renstra',
            'year'              => $year,
            'period_start_year' => 2026,
            'period_end_year'   => 2030,
            'version_no'        => 1,
            'status'            => 'active',
        ]);
    }

    /**
     * Indikator baru + versi aktifnya (struktur, satuan, formula, periode baru).
     */
    protected function makeDashIndicatorVersion(
        string $formulaType = 'persentase_capaian',
        ?int $directionId = null,
    ): IndicatorVersion {
        $satuan = UnitOfMeasure::create(['name' => 'Satuan Dash '.uniqid()]);
        $formula = Formula::create([
            'name'         => 'Formula Dash '.uniqid(),
            'formula_type' => $formulaType,
            'type'         => 'system',
        ]);
        $periode = ReportingPeriod::create([
            'name'             => 'Periode Dash '.uniqid(),
            'periods_per_year' => 4,
        ]);

        $structureId = DB::table('performance_structure')->insertGetId([
            'level_type' => 'Tujuan',
            'name'       => 'Struktur Dash '.uniqid(),
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = DB::table('indicators')->insertGetId([
            'structure_id' => $structureId,
            'name'         => 'Indikator Dash '.uniqid(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return IndicatorVersion::create([
            'indicator_id'        => $indicatorId,
            'unit_of_measure_id'  => $satuan->id,
            'formula_id'          => $formula->id,
            'reporting_period_id' => $periode->id,
            'direction_id'        => $directionId,
            'is_active'           => true,
            'valid_from'          => now(),
        ]);
    }

    protected function makeDashTarget(
        IndicatorVersion $indicatorVersion,
        PlanningDocument $document,
        string $periodLabel = 'Tahun Test',
        int $revisionNo = 1,
        bool $active = true,
    ): Target {
        return Target::create([
            'indicator_version_id' => $indicatorVersion->id,
            'planning_document_id' => $document->id,
            'period_label'         => $periodLabel,
            'target_value'         => 100,
            'revision_no'          => $revisionNo,
            'is_active'            => $active,
            'valid_from'           => now(),
            'created_by'           => null,
        ]);
    }

    /**
     * Realisasi dengan status bebas, disisipkan langsung (tanpa engine) agar
     * timestamp dan status dapat dikontrol. Opsi: realization_value,
     * achievement_pct (boleh null), deviation, updated_at.
     */
    protected function makeDashRealization(
        Target $target,
        string $status,
        ?User $owner = null,
        array $options = [],
    ): Realization {
        $updatedAt = $options['updated_at'] ?? now();

        $id = DB::table('realizations')->insertGetId([
            'target_id'         => $target->id,
            'realization_value' => $options['realization_value'] ?? 50,
            'achievement_pct'   => array_key_exists('achievement_pct', $options) ? $options['achievement_pct'] : 50,
            'deviation'         => $options['deviation'] ?? null,
            'status'            => $status,
            'input_by'          => $owner?->id,
            'input_at'          => now(),
            'created_at'        => $updatedAt,
            'updated_at'        => $updatedAt,
        ]);

        return Realization::query()->findOrFail($id);
    }

    /**
     * Realisasi berstatus disahkan. Bila $finalizedAt tidak null, dibuat
     * baris approval_history ke disahkan dengan acted_at tersebut; bila null,
     * tanpa riwayat (menguji fallback ke updated_at). updated_at default =
     * $finalizedAt, atau now() bila tidak ada.
     */
    protected function makeDashOfficial(
        Target $target,
        ?Carbon $finalizedAt,
        ?User $owner = null,
        array $options = [],
    ): Realization {
        $options['updated_at'] = $options['updated_at'] ?? ($finalizedAt ?? now());

        $realization = $this->makeDashRealization($target, 'disahkan', $owner, $options);

        if ($finalizedAt !== null) {
            $this->makeDashHistory($realization, 'direkap_sekretaris', 'disahkan', null, $finalizedAt);
        }

        return $realization;
    }

    protected function makeDashHistory(
        Realization $realization,
        string $from,
        string $to,
        ?User $actor,
        ?Carbon $actedAt,
    ): ApprovalHistory {
        return ApprovalHistory::create([
            'realization_id' => $realization->id,
            'actor_id'       => $actor?->id,
            'from_status'    => $from,
            'to_status'      => $to,
            'action'         => 'validate',
            'note'           => null,
            'acted_at'       => $actedAt,
        ]);
    }
}