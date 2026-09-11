<?php

namespace Tests\Feature\Api\V1\Structure;

use App\Models\PerformanceStructure;
use App\Models\PlanningDocument;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsViewer(string $role = 'operator'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // --- RBAC ---

    public function test_akses_ditolak_tanpa_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('publik');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/performance-structure')->assertStatus(403);
    }

    public function test_operator_dapat_melihat_daftar_struktur(): void
    {
        $this->actingAsViewer('operator');

        PerformanceStructure::factory()->create(['level_type' => 'Tujuan', 'name' => 'Tujuan A']);

        $this->getJson('/api/v1/performance-structure')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_operator_tidak_dapat_membuat_struktur(): void
    {
        $this->actingAsViewer('operator');

        $this->postJson('/api/v1/performance-structure', [
            'level_type' => 'Tujuan',
            'name' => 'Tujuan Baru',
        ])->assertStatus(403);
    }

    public function test_admin_dapat_membuat_struktur(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/performance-structure', [
            'level_type' => 'Tujuan',
            'name' => 'Tujuan Baru',
            'year' => 2026,
        ])->assertStatus(201)->assertJsonPath('data.name', 'Tujuan Baru');

        $this->assertDatabaseHas('performance_structure', ['name' => 'Tujuan Baru']);
    }

    // --- CRUD ---

    public function test_show_berhasil(): void
    {
        $this->actingAsViewer('sekretaris');

        $item = PerformanceStructure::factory()->create(['name' => 'Sasaran Uji']);

        $this->getJson("/api/v1/performance-structure/{$item->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Sasaran Uji');
    }

    public function test_store_gagal_level_type_invalid(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/performance-structure', [
            'level_type' => 'LevelTidakValid',
            'name' => 'Struktur X',
        ])->assertStatus(422);
    }

    public function test_update_berhasil(): void
    {
        $this->actingAsAdmin();

        $item = PerformanceStructure::factory()->create(['name' => 'Nama Lama']);

        $this->putJson("/api/v1/performance-structure/{$item->id}", [
            'name' => 'Nama Baru',
        ])->assertStatus(200)->assertJsonPath('data.name', 'Nama Baru');
    }

    public function test_created_by_terisi_otomatis_dari_actor(): void
    {
        $actor = $this->actingAsAdmin();

        $this->postJson('/api/v1/performance-structure', [
            'level_type' => 'Program',
            'name' => 'Program Uji',
        ])->assertStatus(201);

        $this->assertDatabaseHas('performance_structure', [
            'name' => 'Program Uji',
            'created_by' => $actor->id,
        ]);
    }

    // --- Hierarchy ---

    public function test_create_root_dan_child_berhasil(): void
    {
        $this->actingAsAdmin();

        $root = PerformanceStructure::factory()->create(['level_type' => 'Tujuan', 'name' => 'Root']);

        $this->postJson('/api/v1/performance-structure', [
            'parent_id' => $root->id,
            'level_type' => 'Sasaran',
            'name' => 'Child',
        ])->assertStatus(201)->assertJsonPath('data.parent_id', $root->id);
    }

    public function test_update_reject_self_parent(): void
    {
        $this->actingAsAdmin();

        $item = PerformanceStructure::factory()->create();

        $this->putJson("/api/v1/performance-structure/{$item->id}", [
            'parent_id' => $item->id,
        ])->assertStatus(422);
    }

    public function test_update_reject_circular_hierarchy(): void
    {
        $this->actingAsAdmin();

        $grandparent = PerformanceStructure::factory()->create(['level_type' => 'Tujuan']);
        $parent = PerformanceStructure::factory()->create(['level_type' => 'Sasaran', 'parent_id' => $grandparent->id]);
        $child = PerformanceStructure::factory()->create(['level_type' => 'Program', 'parent_id' => $parent->id]);

        // grandparent tidak boleh menjadikan child sebagai parent-nya (circular)
        $this->putJson("/api/v1/performance-structure/{$grandparent->id}", [
            'parent_id' => $child->id,
        ])->assertStatus(422);
    }

    public function test_update_valid_hierarchy_berhasil(): void
    {
        $this->actingAsAdmin();

        $newParent = PerformanceStructure::factory()->create(['level_type' => 'Tujuan']);
        $item = PerformanceStructure::factory()->create(['level_type' => 'Sasaran']);

        $this->putJson("/api/v1/performance-structure/{$item->id}", [
            'parent_id' => $newParent->id,
        ])->assertStatus(200)->assertJsonPath('data.parent_id', $newParent->id);
    }

    // --- Active state ---

    public function test_create_dengan_is_active_false(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/performance-structure', [
            'level_type' => 'Kegiatan',
            'name' => 'Kegiatan Nonaktif',
            'is_active' => false,
        ])->assertStatus(201)->assertJsonPath('data.is_active', false);
    }

    // --- planning_document_id relation ---

    public function test_create_dengan_planning_document_id_valid(): void
    {
        $this->actingAsAdmin();

        $doc = PlanningDocument::factory()->create();

        $this->postJson('/api/v1/performance-structure', [
            'level_type' => 'Sub Kegiatan',
            'name' => 'Sub Kegiatan Terkait Dokumen',
            'planning_document_id' => $doc->id,
        ])->assertStatus(201)->assertJsonPath('data.planning_document_id', $doc->id);
    }

    // --- Out of scope confirmation ---

    public function test_tidak_ada_endpoint_delete_fisik(): void
    {
        $this->actingAsAdmin();

        $item = PerformanceStructure::factory()->create();

        $this->deleteJson("/api/v1/performance-structure/{$item->id}")
            ->assertStatus(405);
    }
}