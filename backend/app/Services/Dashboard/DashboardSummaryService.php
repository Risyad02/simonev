<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * M1 — ringkasan angka resmi. Rata-rata capaian hanya dalam satu formula_type
 * dan hanya untuk arah yang dinilai (Owner D3); Netral, tanpa arah, dan capaian
 * null dihitung terpisah. Tidak ada rata-rata gabungan lintas formula_type.
 */
class DashboardSummaryService
{
    /** Nama arah (MeasurementDirectionSeeder) yang membuat capaian layak dirata-rata. */
    private const ASSESSED_DIRECTIONS = [DirectionNames::UP, DirectionNames::DOWN];

    public function __construct(
        private readonly OfficialRealizationQuery $official,
        private readonly DashboardScopeService $scopes,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summarize(User $actor, DashboardScope $scope, array $filters): array
    {
        $official = $this->scopes->applyReadScope($this->official->builder($filters), $scope, $actor);

        [$naik, $turun] = self::ASSESSED_DIRECTIONS;

        $rows = DB::query()
            ->fromSub($official, 'o')
            ->leftJoin('measurement_directions as md', 'md.id', '=', 'o.direction_id')
            ->selectRaw(
                'o.formula_type, o.formula_kind, COUNT(*) AS key_count, '
                .'SUM(CASE WHEN o.achievement_pct IS NULL THEN 1 ELSE 0 END) AS without_percentage, '
                .'SUM(CASE WHEN o.achievement_pct IS NOT NULL AND md.name IN (?, ?) THEN 1 ELSE 0 END) AS averaged_count, '
                .'AVG(CASE WHEN o.achievement_pct IS NOT NULL AND md.name IN (?, ?) THEN o.achievement_pct END) AS average_achievement_pct, '
                .'SUM(CASE WHEN o.achievement_pct IS NOT NULL AND (md.name IS NULL OR md.name NOT IN (?, ?)) THEN 1 ELSE 0 END) AS without_direction_assessment, '
                .'SUM(CASE WHEN o.anchor_has_active_target = 1 THEN 1 ELSE 0 END) AS official_in_universe',
                [$naik, $turun, $naik, $turun, $naik, $turun]
            )
            ->groupBy('o.formula_type', 'o.formula_kind')
            ->orderBy('o.formula_type')
            ->get();

        $groups = $rows
            ->map(fn ($row): array => [
                'formula_type'                 => $row->formula_type,
                'formula_kind'                 => $row->formula_kind,
                'key_count'                    => (int) $row->key_count,
                'without_percentage'           => (int) $row->without_percentage,
                'averaged_count'               => (int) $row->averaged_count,
                'average_achievement_pct'      => $row->average_achievement_pct === null
                    ? null
                    : number_format((float) $row->average_achievement_pct, 4, '.', ''),
                'without_direction_assessment' => (int) $row->without_direction_assessment,
            ])
            ->values();

        return [
            'official' => [
                'key_count'          => $groups->sum('key_count'),
                'without_percentage' => $groups->sum('without_percentage'),
                'groups'             => $groups->all(),
            ],
            'coverage' => $scope->coverageAvailable
                ? $this->coverage((int) $rows->sum('official_in_universe'), $filters)
                : null,
        ];
    }

    /**
     * Cakupan = kunci resmi yang berada di semesta (target jangkar aktif)
     * dibanding seluruh semesta. official_keys berasal dari query yang sama
     * dengan ringkasan (satu snapshot). percentage null bila semesta kosong.
     *
     * @param  array<string, mixed>  $filters
     * @return array{universe_keys: int, official_keys: int, percentage: float|null}
     */
    private function coverage(int $officialKeys, array $filters): array
    {
        $universeKeys = $this->official->universe($filters)->count();

        return [
            'universe_keys' => $universeKeys,
            'official_keys' => $officialKeys,
            'percentage'    => $universeKeys > 0 ? round($officialKeys / $universeKeys * 100, 2) : null,
        ];
    }
}