<?php

namespace App\Services\Target;

use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TargetService
{
    /**
     * Membuat target awal (revision_no = 1).
     *
     * @return array{0: bool, 1: Target|string, 2: int|null}
     */
    public function createInitial(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $existingActive = Target::query()
                ->where('indicator_version_id', $data['indicator_version_id'])
                ->where('period_label', $data['period_label'])
                ->where('is_active', true)
                ->exists();

            if ($existingActive) {
                return [false, 'Target aktif untuk kombinasi indicator_version_id dan period_label ini sudah ada. Gunakan endpoint revisi.', 409];
            }

            $target = Target::create([
                'indicator_version_id' => $data['indicator_version_id'],
                'planning_document_id' => $data['planning_document_id'],
                'period_label'         => $data['period_label'],
                'target_value'         => $data['target_value'],
                'revision_no'          => 1,
                'is_active'            => true,
                'valid_from'           => now(),
                'valid_to'             => null,
                'reason'               => $data['reason'] ?? null,
                'created_by'           => $actor->id,
            ]);

            return [true, $target, null];
        });
    }

    /**
     * Membuat revisi dari target aktif existing.
     *
     * @return array{0: bool, 1: Target|string, 2: int|null}
     */
    public function createRevision(Target $target, array $data, User $actor): array
    {
        return DB::transaction(function () use ($target, $data, $actor) {
            if (! $target->is_active) {
                return [false, 'Revisi hanya dapat dibuat dari target yang sedang aktif.', 409];
            }

            // Safety net kedua (selain FormRequest) untuk invariant reason wajib saat revisi.
            if (empty($data['reason'])) {
                return [false, 'Alasan (reason) wajib diisi untuk revisi target.', 422];
            }

            $target->update([
                'is_active' => false,
                'valid_to'  => now(),
            ]);

            $newTarget = Target::create([
                'indicator_version_id' => $target->indicator_version_id,
                'planning_document_id' => $data['planning_document_id'] ?? $target->planning_document_id,
                'period_label'         => $target->period_label,
                'target_value'         => $data['target_value'],
                'revision_no'          => $target->revision_no + 1,
                'is_active'            => true,
                'valid_from'           => now(),
                'valid_to'             => null,
                'reason'               => $data['reason'],
                'created_by'           => $actor->id,
            ]);

            return [true, $newTarget, null];
        });
    }

    /**
     * Menghapus target secara fisik, hanya jika belum memiliki realization.
     *
     * Catatan: Realization model belum ada (scope Phase 10), sehingga
     * pengecekan dependency dilakukan via Query Builder langsung terhadap
     * tabel realizations, bukan relasi Eloquent.
     *
     * @return array{0: bool, 1: string, 2: int|null}
     */
    public function delete(Target $target): array
    {
        $hasRealization = DB::table('realizations')
            ->where('target_id', $target->id)
            ->exists();

        if ($hasRealization) {
            return [false, 'Target tidak dapat dihapus karena sudah memiliki realisasi.', 409];
        }

        return DB::transaction(function () use ($target) {
            $target->delete();

            return [true, 'Target berhasil dihapus.', null];
        });
    }
}