<?php

namespace App\Services\Dashboard;

use App\Enums\RealizationStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * M2 — pipeline workflow per status. BUKAN angka resmi: semua status dalam
 * read-scope pembaca dihitung, dan direkap_sekretaris bukan status final.
 */
class DashboardPipelineService
{
    /** Urutan tampilan tetap (alur workflow Phase 11). */
    private const ORDER = [
        'draft',
        'diajukan',
        'divalidasi_kasubbid',
        'divalidasi_kabid',
        'direkap_sekretaris',
        'dikembalikan',
        'disahkan',
    ];

    public function __construct(
        private readonly DashboardScopeService $scopes,
    ) {
    }

    /** @return array{statuses: list<array<string, mixed>>, total: int} */
    public function pipeline(User $actor, DashboardScope $scope): array
    {
        $query = DB::table('realizations')
            ->selectRaw('status, COUNT(*) AS status_count, MIN(updated_at) AS oldest_updated_at')
            ->groupBy('status');

        $query = $this->scopes->applyReadScope(
            $query,
            $scope,
            $actor,
            'realizations.id',
            'realizations.status',
            'realizations.input_by',
        );

        $rows = $query->get()->keyBy('status');

        $statuses = [];
        $total = 0;

        foreach (self::ORDER as $value) {
            $status = RealizationStatus::from($value);
            $row = $rows->get($value);
            $count = $row === null ? 0 : (int) $row->status_count;
            $total += $count;

            $statuses[] = [
                'status'            => $value,
                'label'             => $status->label(),
                'is_final'          => $status->isFinal(),
                'count'             => $count,
                'oldest_updated_at' => $row?->oldest_updated_at === null
                    ? null
                    : Carbon::parse($row->oldest_updated_at)->toIso8601String(),
            ];
        }

        return ['statuses' => $statuses, 'total' => $total];
    }
}