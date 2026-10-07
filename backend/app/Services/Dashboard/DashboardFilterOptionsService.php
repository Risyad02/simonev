<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Opsi filter — PROVISIONAL: hanya nilai yang punya angka resmi di dalam
 * read-scope pembaca, sehingga tidak membocorkan data di luar scope. Daftar
 * indikator hanya untuk scope row-level. Periode berupa string bebas dan
 * diurutkan secara abjad, bukan kronologis (G1).
 */
class DashboardFilterOptionsService
{
    public function __construct(
        private readonly OfficialRealizationQuery $official,
        private readonly DashboardScopeService $scopes,
    ) {
    }

    /** @return array<string, mixed> */
    public function options(User $actor, DashboardScope $scope): array
    {
        $official = $this->scopes->applyReadScope($this->official->builder(), $scope, $actor);

        $from = fn () => DB::query()->fromSub($official, 'o');

        return [
            'planning_documents' => $from()
                ->join('planning_documents as pd', 'pd.id', '=', 'o.anchor_planning_document_id')
                ->select(['pd.id', 'pd.document_type', 'pd.year'])
                ->distinct()->orderBy('pd.id')->get()
                ->map(fn ($row): array => [
                    'id'            => (int) $row->id,
                    'document_type' => $row->document_type,
                    'year'          => $row->year === null ? null : (int) $row->year,
                ])->all(),
            'period_labels' => $from()
                ->select('o.period_label')
                ->distinct()->orderBy('o.period_label')
                ->pluck('period_label')->all(),
            'reporting_periods' => $this->idName(
                $from()->join('reporting_periods as rp', 'rp.id', '=', 'o.reporting_period_id')
                    ->select(['rp.id', 'rp.name'])->distinct()->orderBy('rp.id')->get()
            ),
            'directions' => $this->idName(
                $from()->join('measurement_directions as md', 'md.id', '=', 'o.direction_id')
                    ->select(['md.id', 'md.name'])->distinct()->orderBy('md.id')->get()
            ),
            'indicators' => $scope->rowLevel
                ? $this->idName(
                    $from()->join('indicators as i', 'i.id', '=', 'o.indicator_id')
                        ->select(['i.id', 'i.name'])->distinct()->orderBy('i.id')->get()
                )
                : null,
        ];
    }

    /** @return list<array{id: int, name: string}> */
    private function idName($rows): array
    {
        return $rows->map(fn ($row): array => ['id' => (int) $row->id, 'name' => $row->name])->all();
    }
}