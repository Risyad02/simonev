<?php

namespace App\Services\Realization;

use App\Enums\RealizationStatus;
use App\Models\ApprovalHistory;
use App\Models\Realization;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Realization\Workflow\ActorRule;
use App\Services\Realization\Workflow\RealizationWorkflow;
use App\Services\Realization\Workflow\WorkflowAction;
use Illuminate\Support\Facades\DB;

/**
 * Engine tunggal transisi status Realization (Phase 11).
 *
 * Transisi ditentukan dari status TERKINI di database (dibaca ulang di bawah
 * lock) + aksi + registry — tidak pernah dari request. Aktor, waktu,
 * from_status, dan to_status seluruhnya ditentukan server.
 *
 * Catatan concurrency: lockForUpdate() dijamin di MariaDB/MySQL; SQLite
 * (test otomatis) mengabaikannya, sehingga bukti lock nyata diverifikasi
 * manual di MariaDB.
 */
class RealizationApprovalService
{
    public function __construct(
        private readonly RealizationWorkflow $workflow,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @return array{0: bool, 1: Realization|string, 2: int|null}
     */
    public function submit(Realization $realization, User $actor, ?string $note = null): array
    {
        return $this->perform($realization, WorkflowAction::Submit, $actor, $note);
    }

    /**
     * @return array{0: bool, 1: Realization|string, 2: int|null}
     */
    public function approve(Realization $realization, User $actor, ?string $note = null): array
    {
        return $this->perform($realization, WorkflowAction::Approve, $actor, $note);
    }

    /**
     * Mengembalikan realisasi ke pemilik data (status dikembalikan). Catatan
     * wajib ditegakkan engine (422) berdasarkan registry, terlepas dari
     * validasi request.
     *
     * @return array{0: bool, 1: Realization|string, 2: int|null}
     */
    public function sendBack(Realization $realization, User $actor, ?string $note): array
    {
        return $this->perform($realization, WorkflowAction::SendBack, $actor, $note);
    }

    /**
     * Entry point engine. Public agar dapat diuji langsung dan dibungkus
     * method bernama per aksi (approve/sendBack di CP-B2). Controller TIDAK
     * boleh meneruskan aksi yang berasal dari request ke sini.
     *
     * Kode kegagalan:
     *  403 aturan aktor (pemilik/backup, atau bukan-pemilik) tidak terpenuhi
     *  404 realisasi tidak ditemukan
     *  409 tidak ada transisi untuk status saat ini, atau aktor tidak
     *      memegang permission untuk transisi tersebut
     *  422 catatan wajib tetapi kosong
     *
     * @return array{0: bool, 1: Realization|string, 2: int|null}
     */
    public function perform(Realization $realization, WorkflowAction $action, User $actor, ?string $note = null): array
    {
        $note = $this->normalizeNote($note);

        // Pemeriksaan awal tanpa lock (fail-fast). Status pada $realization
        // bisa basi, sehingga hanya aturan yang seragam untuk seluruh
        // transisi aksi ini yang dipakai di sini.
        $uniformRule = $this->uniformActorRule($action);

        if ($uniformRule !== null && ! $this->authorizeActor($uniformRule, $realization, $actor)) {
            return [false, $this->actorDenialMessage($uniformRule), 403];
        }

        return DB::transaction(function () use ($realization, $action, $actor, $note) {
            // Baca ulang di bawah lock: model dari route binding sudah dimuat
            // sebelum lock dan bisa basi.
            $locked = Realization::query()->lockForUpdate()->find($realization->getKey());

            if ($locked === null) {
                return [false, 'Realisasi tidak ditemukan.', 404];
            }

            $from = RealizationStatus::tryFrom((string) $locked->status);
            $transition = $from === null ? null : $this->workflow->find($from, $action);

            if ($transition === null || ! $this->hasAnyPermission($actor, $transition->permissions)) {
                return [false, 'Realisasi tidak berada pada tahap yang dapat Anda proses.', 409];
            }

            if (! $this->authorizeActor($transition->actorRule, $locked, $actor)) {
                return [false, $this->actorDenialMessage($transition->actorRule), 403];
            }

            if ($transition->noteRequired && $note === null) {
                return [false, 'Catatan wajib diisi untuk aksi ini.', 422];
            }

            $locked->status = $transition->to->value;
            $locked->save();

            ApprovalHistory::create([
                'realization_id' => $locked->id,
                'actor_id'       => $actor->id,
                'from_status'    => $from->value,
                'to_status'      => $transition->to->value,
                'action'         => $transition->historyAction->value,
                'note'           => $note,
                'acted_at'       => now(),
            ]);

            $newValue = array_merge(
                ['status' => $transition->to->value],
                $locked->only(['realization_value', 'achievement_pct', 'deviation'])
            );

            if ($locked->input_by !== null && (int) $locked->input_by !== (int) $actor->id) {
                $newValue['owner_id'] = (int) $locked->input_by;
            }

            $this->auditService->log(
                action: ($this->isBackup($actor) ? 'backup_' : '').$transition->auditAction,
                entityType: 'Realization',
                entityId: $locked->id,
                oldValue: ['status' => $from->value],
                newValue: $newValue,
                actor: $actor,
            );

            return [true, $locked, null];
        });
    }

    /**
     * Satu-satunya tempat keputusan aktor (pemilik/backup/bukan-pemilik).
     *
     * TBD-4: predikat unit-scope ditambahkan DI SINI (dan pada query antrean
     * di CP-C) tanpa mengubah registry maupun state machine. Saat ini dimensi
     * unit belum tersedia (Controlled Transitional Authorization).
     */
    private function authorizeActor(ActorRule $rule, Realization $realization, User $actor): bool
    {
        $isOwner = $realization->input_by !== null
            && (int) $realization->input_by === (int) $actor->id;

        return match ($rule) {
            ActorRule::OwnerOrBackup => $isOwner || $this->isBackup($actor),
            ActorRule::NotOwner      => ! $isOwner,
        };
    }

    private function actorDenialMessage(ActorRule $rule): string
    {
        return match ($rule) {
            ActorRule::OwnerOrBackup => 'Anda hanya dapat memproses realisasi milik Anda sendiri.',
            ActorRule::NotOwner      => 'Anda tidak dapat memvalidasi atau mengembalikan realisasi yang Anda input sendiri.',
        };
    }

    /** Aktor backup-only (mis. Admin): punya manage.backup tanpa manage. */
    private function isBackup(User $actor): bool
    {
        return ! $actor->can('realization.manage') && $actor->can('realization.manage.backup');
    }

    /**
     * @param  list<string>  $permissions  bersemantik any-of
     */
    private function hasAnyPermission(User $actor, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($actor->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /** Aturan aktor bila SELURUH transisi suatu aksi memakai aturan yang sama; selain itu null. */
    private function uniformActorRule(WorkflowAction $action): ?ActorRule
    {
        $rules = [];

        foreach ($this->workflow->all() as $transition) {
            if ($transition->action === $action) {
                $rules[$transition->actorRule->value] = $transition->actorRule;
            }
        }

        return count($rules) === 1 ? array_values($rules)[0] : null;
    }

    private function normalizeNote(?string $note): ?string
    {
        $note = $note === null ? null : trim($note);

        return $note === '' ? null : $note;
    }
}