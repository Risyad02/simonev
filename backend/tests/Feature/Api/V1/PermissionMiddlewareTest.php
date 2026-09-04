<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    public function test_user_with_required_permission_can_access_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/test-permission');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Akses permission user.manage berhasil diverifikasi',
            ]);
    }

    public function test_user_without_required_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('operator');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/test-permission');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_protected_endpoint(): void
    {
        $response = $this->getJson('/api/v1/test-permission');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Tidak terautentikasi',
            ]);
    }

    public function test_seeded_roles_and_permissions_are_usable_by_middleware(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->assertTrue($user->can('user.manage'));
        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_sekretaris_role_does_not_have_finalize_permission(): void
    {
        $sekretaris = Role::where('name', 'sekretaris')->first();

        $this->assertFalse(
            $sekretaris->hasPermissionTo('realization.finalize.kadis'),
            'Sekretaris TIDAK BOLEH memiliki permission finalisasi realisasi (§5.1, non-final constraint).'
        );
    }

    public function test_only_kepala_dinas_role_has_finalize_permission(): void
    {
        $permission = Permission::where('name', 'realization.finalize.kadis')->first();

        $roleNames = $permission->roles->pluck('name')->all();

        $this->assertEquals(
            ['kepala_dinas'],
            $roleNames,
            'Permission realization.finalize.kadis hanya boleh dimiliki role kepala_dinas.'
        );
    }
}