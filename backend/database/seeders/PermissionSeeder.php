<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Seluruh permission dipetakan 1:1 terhadap baris RBAC Matrix
     * docs/architecture/README.md §2.4.
     * guard_name 'web' — terverifikasi cocok dengan config/auth.php.
     */
    public function run(): void
    {
        $permissions = [
            // Manajemen Pengguna & Sistem (Super Admin-only)
            'user.manage',
            'system.manage',

            // Master Data — sistem-kritis
            'master-data-kritis.manage',
            'master-data-kritis.view',

            // Kelola Struktur & Indikator
            'structure.manage',
            'structure.view',
            'structure.view.own-subunit',
            'structure.propose-revision',

            // Kelola Target
            'target.manage',
            'target.view',
            'target.discuss',

            // Input Realisasi
            'realization.manage',
            'realization.manage.backup',
            'realization.view',
            'realization.view.cross-unit',

            // Validasi
            'realization.validate.kasubbid',
            'realization.validate.kabid',

            // Rekap & alur Sekretaris (non-final)
            'realization.recap.view',
            'realization.review.sekretaris',
            'realization.correct.sekretaris',
            'realization.recommend.sekretaris',
            'realization.return.sekretaris',

            // Pengesahan final
            'realization.finalize.kadis',

            // Publikasi & Portal Publik
            'publication.manage',
            'public-portal.view',

            // Dashboard
            'dashboard.view.full',
            'dashboard.view.operational',
            'dashboard.view.own-scope',
            'dashboard.view.cross-unit',
            'dashboard.view.strategic-summary',

            // Audit Log
            'audit-log.view.full',
            'audit-log.view',

            // === 4 TBD CR-001 §5.3 — DIBUAT, TIDAK DI-ASSIGN ===
            // Menunggu keputusan CR-001 sebelum di-assign ke role manapun
            // di RolePermissionSeeder. Lihat CLAUDE.md §5.3.
            'master-data-operasional.manage', // TBD-1
            'structure.approve-revision',     // TBD-3
            'target.approve-revision',        // TBD-3
            'public-portal.override',         // TBD-2
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }
}