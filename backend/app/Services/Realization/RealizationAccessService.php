<?php

namespace App\Services\Realization;

use App\Enums\RealizationStatus;
use App\Models\ApprovalHistory;
use App\Models\Realization;
use App\Models\User;
use App\Services\Realization\Workflow\RealizationWorkflow;
use App\Services\Realization\Workflow\WorkflowAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Keputusan akses BACA Realization (Phase 11) dan antrean persetujuan.
 *
 * TBD-4: predikat unit-scope untuk baca ditambahkan DI SINI (canView dan
 * approvalQueue) tanpa mengubah registry maupun state machine. Saat ini
 * dimensi unit belum tersedia (Controlled Transitional Authorization):
 * antrean dan akses berbasis tahap belum difilter per unit.
 */
class RealizationAccessService
{
    public function __construct(
        private readonly RealizationWorkflow $workflow,
    ) {
    }

    /**
     * Status ("tahap") yang menjadi tanggung jawab aktor: status asal transisi
     * approve/return yang permission-nya dipegang aktor. Diturunkan dari
     * registry sehingga tetap satu sumber kebenaran.
     *
     * @return list<RealizationStatus>
     */
    public function stagesFor(User $actor): array
    {
        $stages = [];

        foreach ($this->workflow->all() as $transition) {
            if ($transition->action === WorkflowAction::Submit) {
                continue; // aksi pemilik data, bukan tahap review
            }

            foreach ($transition->permissions as $permission) {
                if ($actor->can($permission)) {
                    $stages[$transition->from->value] = $transition->from;
                    break;
                }
            }
        }

        return array_values($stages);
    }

    /**
     * Realisasi boleh dibuka bila salah satu benar:
     *  1. aktor punya akses lintas unit / rekap (perilaku Phase 10);
     *  2. aktor pemilik data (input_by);
     *  3. status realisasi sedang berada di tahap aktor;
     *  4. aktor pernah bertindak pada realisasi ini (ada di approval_history).
     */
    public function canView(Realization $realization, User $actor): bool
    {
        if ($actor->can('realization.view.cross-unit') || $actor->can('realization.recap.view')) {
            return true;
        }

        if ($realization->input_by !== null && (int) $realization->input_by === (int) $actor->id) {
            return true;
        }

        $status = RealizationStatus::tryFrom((string) $realization->status);

        if ($status !== null && in_array($status, $this->stagesFor($actor), true)) {
            return true;
        }

        return ApprovalHistory::query()
            ->where('realization_id', $realization->id)
            ->where('actor_id', $actor->id)
            ->exists();
    }

    /** Aktor backup-only (mis. Admin): punya manage.backup tanpa manage. */
    public function isBackup(User $actor): bool
    {
        return ! $actor->can('realization.manage') && $actor->can('realization.manage.backup');
    }

    public function isOwner(Realization $realization, User $actor): bool
    {
        return $realization->input_by !== null
            && (int) $realization->input_by === (int) $actor->id;
    }

    /**
     * Pemilik data atau aktor backup-only. Dipakai aksi pemilik di luar
     * transisi status (koreksi nilai, unggah lampiran).
     *
     * TBD-4: predikat unit-scope untuk aksi pemilik ditambahkan di sini;
     * RealizationApprovalService::authorizeActor mendelegasikan ke method ini.
     */
    public function canActAsOwner(Realization $realization, User $actor): bool
    {
        return $this->isOwner($realization, $actor) || $this->isBackup($actor);
    }

    /**
     * Antrean: realisasi yang statusnya berada di tahap aktor. Tanpa filter
     * unit (temporary limitation). Tidak memuat relasi inputBy karena kunci
     * relasinya akan menimpa kolom FK input_by pada JSON.
     */
    public function approvalQueue(User $actor, int $perPage): LengthAwarePaginator
    {
        $stages = array_map(
            fn (RealizationStatus $status): string => $status->value,
            $this->stagesFor($actor)
        );

        return Realization::query()
            ->whereIn('status', $stages)
            ->with('target')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->paginate($perPage);
    }
}