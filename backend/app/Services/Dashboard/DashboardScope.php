<?php

namespace App\Services\Dashboard;

/**
 * Hasil keputusan scope dashboard untuk satu aktor. Murni nilai (tanpa logika).
 * transitional = true selama unit-scope (TBD-4) belum tersedia.
 */
final readonly class DashboardScope
{
    public function __construct(
        public string $mode,
        public bool $readScoped,
        public bool $rowLevel,
        public bool $coverageAvailable,
        public bool $includeDataQuality,
        public bool $transitional,
    ) {
    }
}