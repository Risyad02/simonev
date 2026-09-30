<?php

namespace App\Services\Realization\Workflow;

use App\Enums\ApprovalHistoryAction;
use App\Enums\RealizationStatus;

/**
 * Satu transisi workflow (value object immutable).
 *
 * $permissions bersemantik any-of: aktor cukup memegang salah satunya.
 * Awalan "backup_" pada audit action ditambahkan service saat aksi berjalan.
 */
final readonly class RealizationTransition
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $code,
        public RealizationStatus $from,
        public WorkflowAction $action,
        public RealizationStatus $to,
        public array $permissions,
        public ActorRule $actorRule,
        public bool $noteRequired,
        public ApprovalHistoryAction $historyAction,
        public string $auditAction,
    ) {
    }
}