<?php

namespace App\Services\Realization;

use App\Enums\RealizationStatus;
use App\Models\Realization;
use App\Models\Target;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Formula\Exceptions\FormulaCalculationException;
use App\Services\Formula\FormulaEngine;
use App\Services\Formula\FormulaResult;
use Illuminate\Support\Facades\DB;

class RealizationService
{
    public function __construct(
        private readonly FormulaEngine $formulaEngine,
        private readonly AuditService $auditService,
        private readonly RealizationAccessService $access,
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

        [$formulaResult, $formulaError] = $this->evaluateFormula($target, (float) $data['realization_value']);

        if ($formulaError !== null) {
            return [false, $formulaError, 422];
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
     * Koreksi nilai realisasi (Phase 11, D-E): in-place, hanya saat status
     * dikembalikan, oleh pemilik atau aktor backup. Nilai turunan dihitung
     * ulang terhadap target ASLI realisasi ini (CLAUDE.md §10) — is_active
     * target sengaja tidak diperiksa, karena realisasi yang sudah tercatat
     * tetap terhubung ke versi target saat dicatat. Status tidak berubah;
     * pengajuan ulang adalah aksi terpisah (submit).
     *
     * Nilai lama hanya tersimpan di audit_logs (append-only).
     *
     * @return array{0: bool, 1: Realization|string, 2: int|null}
     */
    public function correctValue(Realization $realization, array $data, User $actor): array
    {
        if (! $this->access->canActAsOwner($realization, $actor)) {
            return [false, 'Anda hanya dapat mengoreksi realisasi milik Anda sendiri.', 403];
        }

        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            return [false, 'Alasan koreksi wajib diisi.', 422];
        }

        $isBackup = $this->access->isBackup($actor);

        return DB::transaction(function () use ($realization, $data, $actor, $reason, $isBackup) {
            // Baca ulang di bawah lock: model dari route binding bisa basi.
            $locked = Realization::query()->lockForUpdate()->find($realization->getKey());

            if ($locked === null) {
                return [false, 'Realisasi tidak ditemukan.', 404];
            }

            if ($locked->status !== RealizationStatus::Dikembalikan->value) {
                return [false, 'Nilai realisasi hanya dapat dikoreksi saat berstatus dikembalikan.', 409];
            }

            $target = Target::with('indicatorVersion.formula')->find($locked->target_id);

            if ($target === null) {
                return [false, 'Target tidak ditemukan.', 404];
            }

            [$formulaResult, $formulaError] = $this->evaluateFormula($target, (float) $data['realization_value']);

            if ($formulaError !== null) {
                return [false, $formulaError, 422];
            }

            $fields = ['realization_value', 'achievement_pct', 'deviation'];
            $oldValue = $locked->only($fields);

            $locked->realization_value = $data['realization_value'];
            $locked->achievement_pct = $formulaResult->achievementPct;
            $locked->deviation = $formulaResult->deviation;
            $locked->save();

            $newValue = $locked->only($fields) + ['reason' => $reason];

            if ($locked->input_by !== null && (int) $locked->input_by !== (int) $actor->id) {
                $newValue['owner_id'] = (int) $locked->input_by;
            }

            $this->auditService->log(
                action: ($isBackup ? 'backup_' : '').'realization_value_correct',
                entityType: 'Realization',
                entityId: $locked->id,
                oldValue: $oldValue,
                newValue: $newValue,
                actor: $actor,
            );

            return [true, $locked, null];
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

    /**
     * Jalur tunggal evaluasi formula untuk create() dan correctValue().
     * Kegagalan perhitungan dipetakan ke pesan (caller menerjemahkannya
     * menjadi 422), tidak pernah bocor menjadi 500.
     *
     * @return array{0: FormulaResult|null, 1: string|null}  [hasil, pesan error]
     */
    private function evaluateFormula(Target $target, float $realizationValue): array
    {
        try {
            return [
                $this->formulaEngine->evaluate(
                    $target->indicatorVersion->formula,
                    (float) $target->target_value,
                    $realizationValue,
                ),
                null,
            ];
        } catch (FormulaCalculationException $e) {
            return [null, 'Perhitungan formula gagal: '.$e->getMessage()];
        }
    }
}