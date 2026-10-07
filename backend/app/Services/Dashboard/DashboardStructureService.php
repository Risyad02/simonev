<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;

/**
 * M4 — agregasi sederhana per node struktur pada satu level. Berbasis hitungan;
 * tidak ada rata-rata capaian lintas indikator (Owner D3).
 *
 * Hierarki tidak dipaksa oleh PerformanceStructureService (tanpa aturan urutan
 * level atau kedalaman), jadi pemetaan memakai recursive CTE dengan batas
 * kedalaman. Setiap node dipetakan ke leluhur TERDEKAT (atau dirinya sendiri)
 * pada level yang diminta agar tidak terhitung ganda. Indikator yang melekat di
 * atas level itu dilaporkan sebagai unattributed, bukan dibuang.
 */
class DashboardStructureService
{
    private const MAX_DEPTH = 10;

    public function __construct(
        private readonly OfficialRealizationQuery $official,
    ) {
    }

    /** @return array{level: string, nodes: list<array<string, mixed>>, unattributed: array<string, mixed>} */
    public function byLevel(string $level): array
    {
        $universe = $this->official->universe();
        $official = $this->official->builder();

        $sql = 'WITH RECURSIVE up (node_id, cur_id, cur_parent, cur_level, depth) AS ('
            .' SELECT id, id, parent_id, level_type, 0 FROM performance_structure'
            .' UNION ALL'
            .' SELECT up.node_id, p.id, p.parent_id, p.level_type, up.depth + 1'
            .' FROM up JOIN performance_structure p ON p.id = up.cur_parent'
            .' WHERE up.depth < '.self::MAX_DEPTH
            .'), nearest AS ('
            .' SELECT node_id, MIN(depth) AS depth FROM up WHERE cur_level = ? GROUP BY node_id'
            .'), lvl AS ('
            .' SELECT up.node_id, up.cur_id AS level_node_id'
            .' FROM up JOIN nearest n ON n.node_id = up.node_id AND n.depth = up.depth'
            .' WHERE up.cur_level = ?'
            .'), u AS ('.$universe->toSql().'), o AS ('.$official->toSql().')'
            .' SELECT lvl.level_node_id AS structure_id,'
            .' COUNT(DISTINCT u.indicator_id) AS indicator_count,'
            .' COUNT(DISTINCT CASE WHEN o.realization_id IS NOT NULL THEN u.indicator_id END) AS indicators_with_official,'
            .' COUNT(*) AS universe_keys,'
            .' SUM(CASE WHEN o.realization_id IS NOT NULL THEN 1 ELSE 0 END) AS official_keys,'
            .' SUM(CASE WHEN o.realization_id IS NOT NULL AND o.achievement_pct IS NULL THEN 1 ELSE 0 END) AS without_percentage'
            .' FROM u'
            .' JOIN indicators i ON i.id = u.indicator_id'
            .' LEFT JOIN lvl ON lvl.node_id = i.structure_id'
            .' LEFT JOIN o ON o.indicator_version_id = u.indicator_version_id AND o.period_label = u.period_label'
            .' GROUP BY lvl.level_node_id';

        // Urutan binding mengikuti urutan teks: nearest, lvl, u, o.
        $rows = collect(DB::select($sql, array_merge(
            [$level, $level],
            $universe->getBindings(),
            $official->getBindings()
        )));

        $byNode = $rows->whereNotNull('structure_id')->keyBy(fn ($row) => (int) $row->structure_id);
        $unattributed = $rows->first(fn ($row) => $row->structure_id === null);

        $nodes = DB::table('performance_structure')
            ->where('level_type', $level)
            ->orderBy('id')
            ->get(['id', 'name', 'level_type'])
            ->map(fn ($node): array => [
                'structure_id' => (int) $node->id,
                'name'         => $node->name,
                'level_type'   => $node->level_type,
            ] + $this->entry($byNode->get((int) $node->id)))
            ->all();

        return [
            'level'        => $level,
            'nodes'        => $nodes,
            'unattributed' => $this->entry($unattributed),
        ];
    }

    /** @return array<string, mixed> */
    private function entry(?object $row): array
    {
        $universe = $row === null ? 0 : (int) $row->universe_keys;
        $official = $row === null ? 0 : (int) $row->official_keys;

        return [
            'indicator_count'          => $row === null ? 0 : (int) $row->indicator_count,
            'indicators_with_official' => $row === null ? 0 : (int) $row->indicators_with_official,
            'coverage'                 => [
                'universe_keys' => $universe,
                'official_keys' => $official,
                'percentage'    => $universe > 0 ? round($official / $universe * 100, 2) : null,
            ],
            'without_percentage'       => $row === null ? 0 : (int) $row->without_percentage,
        ];
    }
}