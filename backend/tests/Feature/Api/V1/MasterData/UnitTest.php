<?php

namespace Tests\Feature\Api\V1\MasterData;

use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function userWithOperasionalPermission(): User
    {
        $user = User::factory()->create();
        $permission = Permission::where('name', 'master-data-operasional.manage')
            ->where('guard_name', 'web')->firstOrFail();
        $user->givePermissionTo($permission);

        return $user;
    }

    public function test_akses_ditolak_tanpa_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/master-data/units')->assertStatus(403);
    }

    public function test_index_dan_show_berhasil(): void
    {
        $actor = $this->userWithOperasionalPermission();
        Sanctum::actingAs($actor, ['*']);

        $unit = Unit::create(['code' => 'BID-01', 'name' => 'Bidang Satu']);

        $this->getJson('/api/v1/master-data/units')
            ->assertStatus(200)->assertJsonPath('success', true);

        $this->getJson("/api/v1/master-data/units/{$unit->id}")
            ->assertStatus(200)->assertJsonPath('data.name', 'Bidang Satu');
    }

    public function test_store_dan_update_berhasil(): void
    {
        $actor = $this->userWithOperasionalPermission();
        Sanctum::actingAs($actor, ['*']);

        $this->postJson('/api/v1/master-data/units', [
            'code' => 'BID-02',
            'name' => 'Bidang Dua',
        ])->assertStatus(201)->assertJsonPath('data.code', 'BID-02');

        $unit = Unit::where('code', 'BID-02')->firstOrFail();

        $this->putJson("/api/v1/master-data/units/{$unit->id}", [
            'name' => 'Bidang Dua Diperbarui',
        ])->assertStatus(200)->assertJsonPath('data.name', 'Bidang Dua Diperbarui');
    }

    public function test_deactivate_berhasil_tanpa_user_atau_child_aktif(): void
    {
        $actor = $this->userWithOperasionalPermission();
        Sanctum::actingAs($actor, ['*']);

        $unit = Unit::create(['code' => 'BID-03', 'name' => 'Bidang Tiga']);

        $this->patchJson("/api/v1/master-data/units/{$unit->id}/deactivate")
            ->assertStatus(200)->assertJsonPath('data.is_active', false);
    }

    public function test_deactivate_ditolak_jika_ada_user_aktif(): void
    {
        $actor = $this->userWithOperasionalPermission();
        Sanctum::actingAs($actor, ['*']);

        $unit = Unit::create(['code' => 'BID-04', 'name' => 'Bidang Empat']);

        // unit_id sengaja TIDAK dimasukkan lewat mass assignment (bukan $fillable
        // sesuai keputusan Phase 5 §5.4) — pakai assignment eksplisit + save().
        $activeUser = User::factory()->create();
        $activeUser->unit_id = $unit->id;
        $activeUser->save();

        $this->patchJson("/api/v1/master-data/units/{$unit->id}/deactivate")
            ->assertStatus(409);

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'is_active' => true]);
    }

    public function test_deactivate_ditolak_jika_ada_child_aktif(): void
    {
        $actor = $this->userWithOperasionalPermission();
        Sanctum::actingAs($actor, ['*']);

        $parent = Unit::create(['code' => 'BID-05', 'name' => 'Bidang Lima']);
        Unit::create(['code' => 'SUB-05', 'name' => 'Sub Bidang Lima', 'parent_unit_id' => $parent->id]);

        $this->patchJson("/api/v1/master-data/units/{$parent->id}/deactivate")
            ->assertStatus(409);
    }

    public function test_activate_ditolak_jika_parent_nonaktif(): void
    {
        $actor = $this->userWithOperasionalPermission();
        Sanctum::actingAs($actor, ['*']);

        $parent = Unit::create(['code' => 'BID-06', 'name' => 'Bidang Enam', 'is_active' => false]);
        $child = Unit::create([
            'code' => 'SUB-06',
            'name' => 'Sub Bidang Enam',
            'parent_unit_id' => $parent->id,
            'is_active' => false,
        ]);

        $this->patchJson("/api/v1/master-data/units/{$child->id}/activate")
            ->assertStatus(409);
    }

    public function test_activate_berhasil_jika_parent_aktif_atau_tanpa_parent(): void
    {
        $actor = $this->userWithOperasionalPermission();
        Sanctum::actingAs($actor, ['*']);

        $unit = Unit::create(['code' => 'BID-07', 'name' => 'Bidang Tujuh', 'is_active' => false]);

        $this->patchJson("/api/v1/master-data/units/{$unit->id}/activate")
            ->assertStatus(200)->assertJsonPath('data.is_active', true);
    }

    public function test_tidak_ada_endpoint_delete_fisik(): void
    {
        $actor = $this->userWithOperasionalPermission();
        Sanctum::actingAs($actor, ['*']);

        $unit = Unit::create(['code' => 'BID-08', 'name' => 'Bidang Delapan']);

        $this->deleteJson("/api/v1/master-data/units/{$unit->id}")
            ->assertStatus(405);
    }
}