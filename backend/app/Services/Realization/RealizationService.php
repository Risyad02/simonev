<?php

namespace App\Services\Realization;

use App\Models\Realization;
use App\Models\Target;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Formula\Exceptions\FormulaCalculationException;
use App\Services\Formula\FormulaEngine;
use Illuminate\Support\Facades\DB;

class RealizationService
{
    public function __construct(
        private readonly FormulaEngine $formulaEngine,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @return array{0: bool, 1: Realization|string, 2: int|null}
     */
    public function create(array $data, User $actor): array
    {
        $target = Target::with('indicatorVersion.formula')->find($data['target_id']);

        if (! $target) {
            return [false, 'Target tidak ditemukan.', 404];
        }

        if (! $target->is_active) {
            return [false, 'Realisasi tidak dapat diinput pada target yang sudah tidak aktif (sudah direvisi). Gunakan target aktif terbaru.', 409];
        }

        try {
            $formulaResult = $this->formulaEngine->evaluate(
                $target->indicatorVersion->formula,
                (float) $target->target_value,
                (float) $data['realization_value'],
            );
        } catch (FormulaCalculationException $e) {
            return [false, 'Perhitungan formula gagal: '.$e->getMessage(), 422];
        }

        $isBackup = ! $actor->can('realization.manage') && $actor->can('realization.manage.backup');

        return DB::transaction(function () use ($data, $actor, $target, $formulaResult, $isBackup) {
            $realization = Realization::create([
                'target_id'         => $target->id,
                'realization_value' => $data['realization_value'],
                'achievement_pct'   => $formulaResult->achievementPct,
                'deviation'         => $formulaResult->deviation,
                'status'            => 'draft',
                'input_by'          => $actor->id,
                'input_at'          => now(),
            ]);

            $this->auditService->log(
                action: $isBackup ? 'backup_operator_input' : 'realization_input',
                entityType: 'Realization',
                entityId: $realization->id,
                oldValue: null,
                newValue: $realization->only(['target_id', 'realization_value', 'achievement_pct', 'deviation', 'status']),
                actor: $actor,
            );

            return [true, $realization, null];
        });
    }

    /**
     * Query scope berdasarkan permission actor — ownership filtering
     * ditegakkan di sini, bukan hanya di middleware. Mengembalikan
     * null khusus untuk kasus D3 (unit-scope terblokir sampai TBD-4
     * CR-001 selesai) — Controller menerjemahkannya jadi 403 eksplisit,
     * bukan diperlakukan sebagai koleksi kosong.
     */
    public function listFor(User $actor)
    {
        $query = Realization::query();

        if ($actor->can('realization.view.cross-unit') || $actor->can('realization.recap.view')) {
            return $query->get();
        }

        if ($actor->can('realization.view.own')) {
            return $query->where('input_by', $actor->id)->get();
        }

        if ($actor->can('realization.view')) {
            return null;
        }

        return collect();
    }
}