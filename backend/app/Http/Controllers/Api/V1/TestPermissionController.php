<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;

class TestPermissionController extends BaseController
{
    /**
     * Endpoint internal untuk verifikasi RBAC end-to-end (Sanctum + Spatie).
     * BUKAN bagian dari API bisnis SIMONEV — hanya infrastruktur testing Phase 5.
     */
    public function check(Request $request)
    {
        return $this->success([
            'user' => $request->user()->only('id', 'name', 'email'),
            'permissions' => $request->user()->getAllPermissions()->pluck('name'),
        ], 'Akses permission user.manage berhasil diverifikasi');
    }
}