<?php

namespace App\Services\Realization\Workflow;

use App\Enums\ApprovalHistoryAction as History;
use App\Enums\RealizationStatus as Status;
use LogicException;

/**
 * Registry transisi workflow — single source of truth (Phase 11).
 * Murni data: tidak ada otorisasi, I/O, atau akses database di sini.
 */
final class RealizationWorkflow
{
    /** @var list<RealizationTransition> */
    private array $transitions;

    /** @var array<string, RealizationTransition> */
    private array $index = [];

    /**
     * @param  list<RealizationTransition>|null  $transitions  null = definisi resmi Phase 11
     */
    public function __construct(?array $transitions = null)
    {
        $this->transitions = array_values($transitions ?? self::definitions());

        foreach ($this->transitions as $transition) {
            $key = self::key($transition->from, $transition->action);

            if (isset($this->index[$key])) {
                throw new LogicException("Transisi ganda pada registry workflow: {$key}.");
            }

            $this->index[$key] = $transition;
        }
    }

    /** @return list<RealizationTransition> */
    public function all(): array
    {
        return $this->transitions;
    }

    public function find(Status $from, WorkflowAction $action): ?RealizationTransition
    {
        return $this->index[self::key($from, $action)] ?? null;
    }

    /** @return list<RealizationTransition> */
    public function outgoing(Status $from): array
    {
        return array_values(array_filter(
            $this->transitions,
            fn (RealizationTransition $t): bool => $t->from === $from
        ));
    }

    /** @return list<string> Nama permission unik yang dirujuk registry, terurut. */
    public function permissions(): array
    {
        $names = [];

        foreach ($this->transitions as $transition) {
            foreach ($transition->permissions as $permission) {
                $names[$permission] = true;
            }
        }

        $names = array_keys($names);
        sort($names);

        return $names;
    }

    private static function key(Status $from, WorkflowAction $action): string
    {
        return $from->value.'|'.$action->value;
    }

    /** @return list<RealizationTransition> */
    private static function definitions(): array
    {
        $manage = ['realization.manage', 'realization.manage.backup'];

        return [
            new RealizationTransition(
                code: 'T1',
                from: Status::Draft,
                action: WorkflowAction::Submit,
                to: Status::Diajukan,
                permissions: $manage,
                actorRule: ActorRule::OwnerOrBackup,
                noteRequired: false,
                historyAction: History::Submit,
                auditAction: 'realization_submit',
            ),
            new RealizationTransition(
                code: 'T2',
                from: Status::Dikembalikan,
                action: WorkflowAction::Submit,
                to: Status::Diajukan,
                permissions: $manage,
                actorRule: ActorRule::OwnerOrBackup,
                noteRequired: false,
                historyAction: History::Submit,
                auditAction: 'realization_resubmit',
            ),
            new RealizationTransition(
                code: 'T3',
                from: Status::Diajukan,
                action: WorkflowAction::Approve,
                to: Status::DivalidasiKasubbid,
                permissions: ['realization.validate.kasubbid'],
                actorRule: ActorRule::NotOwner,
                noteRequired: false,
                historyAction: History::Validate,
                auditAction: 'realization_validate_kasubbid',
            ),
            new RealizationTransition(
                code: 'T4',
                from: Status::DivalidasiKasubbid,
                action: WorkflowAction::Approve,
                to: Status::DivalidasiKabid,
                permissions: ['realization.validate.kabid'],
                actorRule: ActorRule::NotOwner,
                noteRequired: false,
                historyAction: History::Validate,
                auditAction: 'realization_validate_kabid',
            ),
            new RealizationTransition(
                code: 'T5',
                from: Status::DivalidasiKabid,
                action: WorkflowAction::Approve,
                to: Status::DirekapSekretaris,
                permissions: ['realization.recommend.sekretaris'],
                actorRule: ActorRule::NotOwner,
                noteRequired: false,
                historyAction: History::Recap,
                auditAction: 'realization_recommend_sekretaris',
            ),
            new RealizationTransition(
                code: 'T6',
                from: Status::DirekapSekretaris,
                action: WorkflowAction::Approve,
                to: Status::Disahkan,
                permissions: ['realization.finalize.kadis'],
                actorRule: ActorRule::NotOwner,
                noteRequired: false,
                historyAction: History::Approve,
                auditAction: 'realization_finalize_kadis',
            ),
            new RealizationTransition(
                code: 'R1',
                from: Status::Diajukan,
                action: WorkflowAction::SendBack,
                to: Status::Dikembalikan,
                permissions: ['realization.validate.kasubbid'],
                actorRule: ActorRule::NotOwner,
                noteRequired: true,
                historyAction: History::Reject,
                auditAction: 'realization_return_kasubbid',
            ),
            new RealizationTransition(
                code: 'R2',
                from: Status::DivalidasiKasubbid,
                action: WorkflowAction::SendBack,
                to: Status::Dikembalikan,
                permissions: ['realization.validate.kabid'],
                actorRule: ActorRule::NotOwner,
                noteRequired: true,
                historyAction: History::Reject,
                auditAction: 'realization_return_kabid',
            ),
            new RealizationTransition(
                code: 'R3',
                from: Status::DivalidasiKabid,
                action: WorkflowAction::SendBack,
                to: Status::Dikembalikan,
                permissions: ['realization.return.sekretaris'],
                actorRule: ActorRule::NotOwner,
                noteRequired: true,
                historyAction: History::Koreksi,
                auditAction: 'realization_return_sekretaris',
            ),
            new RealizationTransition(
                code: 'R4',
                from: Status::DirekapSekretaris,
                action: WorkflowAction::SendBack,
                to: Status::Dikembalikan,
                permissions: ['realization.finalize.kadis'],
                actorRule: ActorRule::NotOwner,
                noteRequired: true,
                historyAction: History::Reject,
                auditAction: 'realization_return_kadis',
            ),
        ];
    }
}