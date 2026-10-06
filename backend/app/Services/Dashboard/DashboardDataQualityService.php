<?php

namespace App\Services\Dashboard;

use App\Enums\RealizationStatus;
use Illuminate\Support\Facades\DB;

/**
 * M5 — hitungan masalah integritas data. Hanya hitungan, tanpa baris, dan
 * tanpa scope: pemanggil wajib memastikan mode scope mengizinkan
 * (DashboardScope::includeDataQuality).
 *
 * Seluruhnya adalah dampak yang diketahui dari keputusan sementara: periode
 * berupa string bebas (G1), realisasi ganda per target (D1), tidak ada
 * constraint target aktif tunggal, dan versi indikator yang tidak menonaktifkan
 * target lamanya (G15).
 */
class DashboardDataQualityService
{
    private const FORMULA_PERCENTAGE = 'persentase_capaian';
    private const FORMULA_TARGET_PER_REALIZATION = 'target_per_realisasi';

    public function __construct(
        private readonly OfficialRealizationQuery $official,
    ) {
    }

    /** @return array<string, array<string, int>> */
    public function report(): array
    {
        $targets = DB::query()
            ->fromSub(
                DB::table('targets')
                    ->selectRaw('indicator_version_id, period_label, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_count')
                    ->groupBy('indicator_version_id', 'period_label'),
                'k'
            )
            ->selectRaw(
                'SUM(CASE WHEN k.active_count > 1 THEN 1 ELSE 0 END) AS multiple_active, '
                .'SUM(CASE WHEN k.active_count = 0 THEN 1 ELSE 0 END) AS without_active'
            )
            ->first();

        $conflicting = DB::query()
            ->fromSub(
                DB::table('realizations as r')
                    ->join('targets as t', 't.id', '=', 'r.target_id')
                    ->where('r.status', RealizationStatus::Disahkan->value)
                    ->groupBy('t.indicator_version_id', 't.period_label')
                    ->havingRaw('COUNT(*) > 1')
                    ->select('t.indicator_version_id', 't.period_label'),
                'c'
            )
            ->count();

        $universe = DB::query()
            ->fromSub($this->official->universe(), 'u')
            ->selectRaw(
                'COUNT(*) AS universe_keys, '
                .'SUM(CASE WHEN u.indicator_version_is_active = 0 THEN 1 ELSE 0 END) AS on_inactive, '
                .'COUNT(DISTINCT CASE WHEN u.direction_id IS NULL THEN u.indicator_version_id END) AS without_direction'
            )
            ->first();

        $split = DB::query()
            ->fromSub(
                DB::query()
                    ->fromSub($this->official->universe(), 'u')
                    ->select('u.indicator_id', 'u.period_label')
                    ->groupBy('u.indicator_id', 'u.period_label')
                    ->havingRaw('COUNT(*) > 1'),
                's'
            )
            ->count();

        $official = DB::query()
            ->fromSub($this->official->builder(), 'o')
            ->leftJoin('measurement_directions as md', 'md.id', '=', 'o.direction_id')
            ->selectRaw(
                'SUM(CASE WHEN o.achievement_pct IS NULL THEN 1 ELSE 0 END) AS without_percentage, '
                .'SUM(CASE WHEN (md.name = ? AND o.formula_type = ?) OR (md.name = ? AND o.formula_type = ?) THEN 1 ELSE 0 END) AS mismatch, '
                .'SUM(CASE WHEN o.anchor_has_active_target = 1 THEN 1 ELSE 0 END) AS official_in_universe',
                [
                    DirectionNames::UP, self::FORMULA_TARGET_PER_REALIZATION,
                    DirectionNames::DOWN, self::FORMULA_PERCENTAGE,
                ]
            )
            ->first();

        $universeKeys = (int) $universe->universe_keys;

        return [
            'keys' => [
                'universe_keys'                 => $universeKeys,
                'universe_without_official'     => $universeKeys - (int) $official->official_in_universe,
                'conflicting_official'          => $conflicting,
                'multiple_active_targets'       => (int) $targets->multiple_active,
                'without_active_target'         => (int) $targets->without_active,
                'on_inactive_indicator_version' => (int) $universe->on_inactive,
                'split_across_versions'         => $split,
            ],
            'official' => [
                'without_percentage'         => (int) $official->without_percentage,
                'direction_formula_mismatch' => (int) $official->mismatch,
            ],
            'indicator_versions' => [
                'without_direction' => (int) $universe->without_direction,
            ],
        ];
    }
}