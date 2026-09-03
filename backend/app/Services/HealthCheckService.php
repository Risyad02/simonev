<?php

namespace App\Services;

/**
 * Contoh/referensi pola Service Layer SIMONEV.
 *
 * Pola: Controller memanggil Service untuk business logic;
 * Service memanggil Eloquent Model langsung (tanpa Repository
 * terpisah — lihat CLAUDE.md §3 prinsip Maintainability &
 * docs/architecture/README.md §5 Coding Standard).
 *
 * Service ini tidak menyentuh database (belum ada domain data
 * di Phase 4) — murni demonstrasi struktur untuk Phase 6 dst.
 */
class HealthCheckService
{
    public function getStatus(): array
    {
        return [
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
            'checked_by' => 'HealthCheckService',
        ];
    }
}