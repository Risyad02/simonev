<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * M3 — daftar angka resmi per indikator. Aturan resmi dan read-scope berasal
 * dari OfficialRealizationQuery dan DashboardScopeService; target_value adalah
 * target yang dipakai mengukur realisasi resmi itu (bukan target jangkar).
 */
class DashboardIndicatorListService
{
    public function __construct(
        private readonly OfficialRealizationQuery $official,
        private readonly DashboardScopeService $scopes,
    ) {
    }

    /** @param  array<string, mixed>  $filters */
    public function paginate(User $actor, DashboardScope $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $official = $this->scopes->applyReadScope($this->official->builder($filters), $scope, $actor);

        $paginator = DB::query()
            ->fromSub($official, 'o')
            ->join('targets as t', 't.id', '=', 'o.target_id')
            ->join('indicators as i', 'i.id', '=', 'o.indicator_id')
            ->leftJoin('measurement_directions as md', 'md.id', '=', 'o.direction_id')
            ->select([
                'o.indicator_id',
                'i.name AS indicator_name',
                'o.indicator_version_id',
                'o.period_label',
                't.target_value',
                'o.realization_id',
                'o.realization_value',
                'o.achievement_pct',
                'o.deviation',
                'md.name AS direction',
                'o.formula_type',
                'o.target_superseded',
            ])
            ->orderBy('o.indicator_id')
            ->orderBy('o.period_label')
            ->orderBy('o.indicator_version_id')
            ->orderBy('o.realization_id')
            ->paginate($perPage);

        $paginator->withQueryString()->through(fn (object $row): array => $this->present($row));

        return $paginator;
    }

    /** @return array<string, mixed> */
    private function present(object $row): array
    {
        return [
            'indicator_id'         => (int) $row->indicator_id,
            'indicator_name'       => $row->indicator_name,
            'indicator_version_id' => (int) $row->indicator_version_id,
            'period_label'         => $row->period_label,
            'target_value'         => $this->decimal($row->target_value),
            'realization_id'       => (int) $row->realization_id,
            'realization_value'    => $this->decimal($row->realization_value),
            'achievement_pct'      => $this->decimal($row->achievement_pct),
            'deviation'            => $this->decimal($row->deviation),
            'direction'            => $row->direction,
            'formula_type'         => $row->formula_type,
            'target_superseded'    => (bool) $row->target_superseded,
        ];
    }

    /** Desimal 4 angka sebagai string, seperti JSON Realization; null tetap null. */
    private function decimal(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && preg_match('/^-?\d+\.\d{4}$/', $value) === 1) {
            return $value;
        }

        return number_format((float) $value, 4, '.', '');
    }
}