<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Assign permission ke role sesuai RBAC Matrix §2.4.
     *
     * PENTING: 4 permission TBD CR-001 (§5.3) SENGAJA TIDAK di-assign
     * ke role manapun di sini. Permission tersebut sudah dibuat di
     * PermissionSeeder (exist di DB) tapi menunggu keputusan CR-001
     * sebelum diberikan ke role. JANGAN tambahkan assignment untuk:
     * - master-data-operasional.manage (TBD-1)
     * - structure.approve-revision     (TBD-3)
     * - target.approve-revision        (TBD-3)
     * - public-portal.override         (TBD-2)
     */
    public function run(): void
    {
        $matrix = [
            'super_admin' => [
                'user.manage',
                'system.manage',
                'master-data-kritis.manage',
                'structure.view',
                'target.view',
                'realization.recap.view',
                'dashboard.view.full',
                'audit-log.view.full',
                'indicator.view',
            ],

            'admin' => [
                'master-data-kritis.view',
                'structure.manage',
                'target.manage',
                'realization.manage.backup',
                'realization.recap.view',
                'publication.manage',
                'dashboard.view.operational',
                'audit-log.view',
                'indicator.manage', 
            ],

            'operator' => [
                'structure.view',
                'realization.manage',
                'dashboard.view.own-scope',
                'indicator.view',
            ],

            'kepala_sub_bidang' => [
                'structure.view.own-subunit',
                'realization.view',
                'realization.validate.kasubbid',
                'dashboard.view.own-scope',
            ],

            'kepala_bidang' => [
                'structure.propose-revision',
                'target.discuss',
                'realization.view',
                'realization.validate.kabid',
                'dashboard.view.own-scope',
            ],

            'sekretaris' => [
                'structure.view',
                'target.discuss',
                'realization.view.cross-unit',
                'realization.recap.view',
                'realization.review.sekretaris',
                'realization.correct.sekretaris',
                'realization.recommend.sekretaris',
                'realization.return.sekretaris',
                'dashboard.view.cross-unit',
                'indicator.view',
            ],

            'kepala_dinas' => [
                'target.view',
                'realization.view',
                'realization.recap.view',
                'realization.finalize.kadis',
                'dashboard.view.full',
                'audit-log.view',
            ],

            'pimpinan' => [
                'structure.view',
                'target.view',
                'realization.view',
                'realization.recap.view',
                'dashboard.view.strategic-summary',
                'indicator.view',
            ],

            'publik' => [
                'public-portal.view',
            ],
        ];

        foreach ($matrix as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                $this->command->error("Role '{$roleName}' tidak ditemukan. Jalankan RoleSeeder terlebih dahulu.");
                continue;
            }

            $role->syncPermissions($permissions);
        }
    }
}