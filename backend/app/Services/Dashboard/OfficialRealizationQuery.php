<?php

namespace App\Services\Dashboard;

use App\Enums\RealizationStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Realisasi RESMI untuk dashboard — TEMPORARY DOMAIN RULE (Owner D1):
 *  - hanya status disahkan;
 *  - kunci = (indicator_version_id, period_label);
 *  - per kunci dipilih satu realisasi disahkan TERBARU yang menggantikan yang
 *    lama (bukan cicilan/akumulasi);
 *  - waktu pengesahan = MAX(COALESCE(acted_at, created_at)) dari
 *    approval_history ke disahkan, cadangan realizations.updated_at,
 *    tie-break id DESC;
 *  - target jangkar kunci = target aktif, atau revisi tertinggi bila tak ada;
 *  - capaian memakai nilai tersimpan, tidak dihitung ulang (CLAUDE.md §10).
 *
 * Peringkat dihitung GLOBAL; scope (DashboardScopeService) dan filter dipasang
 * SESUDAH peringkat. Ini bukan klaim bahwa model data sudah mendukung
 * periode/versioning secara penuh (G1, G2).
 *
 * Alias tetap: w (pemenang), a (jangkar), iv, i, f.
 */
class OfficialRealizationQuery
{
    public const RULE = 'temporary_latest_disahkan';
    public const KEY = 'indicator_version_id+period_label';

    /** Filter yang diizinkan => kolom (semua stabil per kunci). */
    private const FILTER_COLUMNS = [
        'planning_document_id' => 'a.anchor_planning_document_id',
        'period_label'         => 'w.period_label',
        'indicator_id'         => 'iv.indicator_id',
        'reporting_period_id'  => 'iv.reporting_period_id',
        'direction_id'         => 'iv.direction_id',
    ];

    /**
     * @param  array<string, mixed>  $filters  kunci tidak dikenal ditolak; nilai null diabaikan
     */
    public function builder(array $filters = []): Builder
    {
        $unknown = array_diff(array_keys($filters), array_keys(self::FILTER_COLUMNS));

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Filter dashboard tidak dikenal: '.implode(', ', $unknown).'.'
            );
        }

        $finalized = DB::table('approval_history')
            ->selectRaw('realization_id, MAX(COALESCE(acted_at, created_at)) AS finalized_at')
            ->where('to_status', RealizationStatus::Disahkan->value)
            ->groupBy('realization_id');

        $ranked = DB::table('realizations as r')
            ->join('targets as t', 't.id', '=', 'r.target_id')
            ->leftJoinSub($finalized, 'h', 'h.realization_id', '=', 'r.id')
            ->where('r.status', RealizationStatus::Disahkan->value)
            ->selectRaw(
                'r.id AS realization_id, r.target_id, r.status AS status, '
                .'t.indicator_version_id, t.period_label, t.revision_no AS measured_revision_no, '
                .'r.realization_value, r.achievement_pct, r.deviation, r.input_by, '
                .'COALESCE(h.finalized_at, r.updated_at) AS finalized_at, '
                .'ROW_NUMBER() OVER (PARTITION BY t.indicator_version_id, t.period_label '
                .'ORDER BY COALESCE(h.finalized_at, r.updated_at) DESC, r.id DESC) AS rn'
            );

        $anchorRanked = DB::table('targets as tg')
            ->selectRaw(
                'tg.id AS anchor_target_id, tg.indicator_version_id, tg.period_label, '
                .'tg.revision_no AS anchor_revision_no, '
                .'tg.planning_document_id AS anchor_planning_document_id, '
                .'tg.is_active AS anchor_is_active, '
                .'ROW_NUMBER() OVER (PARTITION BY tg.indicator_version_id, tg.period_label '
                .'ORDER BY tg.is_active DESC, tg.revision_no DESC, tg.id DESC) AS arn'
            );

        $anchor = DB::query()
            ->fromSub($anchorRanked, 'ax')
            ->where('ax.arn', 1)
            ->select([
                'ax.anchor_target_id',
                'ax.indicator_version_id',
                'ax.period_label',
                'ax.anchor_revision_no',
                'ax.anchor_planning_document_id',
                'ax.anchor_is_active',
            ]);

        $query = DB::query()
            ->fromSub($ranked, 'w')
            ->joinSub($anchor, 'a', function (JoinClause $join) {
                $join->on('a.indicator_version_id', '=', 'w.indicator_version_id')
                    ->on('a.period_label', '=', 'w.period_label');
            })
            ->join('indicator_versions as iv', 'iv.id', '=', 'w.indicator_version_id')
            ->join('indicators as i', 'i.id', '=', 'iv.indicator_id')
            ->join('formulas as f', 'f.id', '=', 'iv.formula_id')
            ->where('w.rn', 1)
            ->selectRaw(
                'w.realization_id, w.target_id, w.indicator_version_id, w.period_label, w.status, '
                .'w.realization_value, w.achievement_pct, w.deviation, w.input_by, w.finalized_at, '
                .'w.measured_revision_no AS measured_against_revision_no, '
                .'a.anchor_target_id, a.anchor_revision_no, a.anchor_planning_document_id, '
                .'a.anchor_is_active AS anchor_has_active_target, '
                .'CASE WHEN w.target_id <> a.anchor_target_id THEN 1 ELSE 0 END AS target_superseded, '
                .'iv.indicator_id, iv.is_active AS indicator_version_is_active, '
                .'iv.reporting_period_id, iv.direction_id, i.structure_id, '
                .'f.formula_type, f.type AS formula_kind'
            );

        foreach ($filters as $name => $value) {
            if ($value !== null) {
                $query->where(self::FILTER_COLUMNS[$name], $value);
            }
        }

        return $query;
    }
}