<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    /**
     * Mencatat satu baris audit log.
     *
     * PENTING: method ini TIDAK membungkus DB::transaction() sendiri.
     * Pemanggil (service domain, mis. RealizationService) bertanggung
     * jawab memanggil ini di dalam transaction miliknya sendiri, sehingga
     * audit log ikut rollback apabila transaksi domain gagal.
     *
     * entity_type memakai short class basename ('Target', 'Realization'),
     * bukan FQCN. action memakai snake_case bebas, bukan enum/lookup DB.
     */
    public function log(
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldValue,
        ?array $newValue,
        ?User $actor,
    ): AuditLog {
        return AuditLog::create([
            'user_id'     => $actor?->id,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_value'   => $oldValue,
            'new_value'   => $newValue,
        ]);
    }
}