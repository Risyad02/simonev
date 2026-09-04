<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * 9 role sesuai CR-001 (CLAUDE.md §RBAC).
     * guard_name 'web' — terverifikasi cocok dengan config/auth.php
     * (defaults.guard = 'web'), aman digunakan bersama auth:sanctum.
     */
    public function run(): void
    {
        $roles = [
            'super_admin',
            'admin',
            'operator',
            'kepala_sub_bidang',
            'kepala_bidang',
            'sekretaris',
            'kepala_dinas',
            'pimpinan',
            'publik',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }
    }
}