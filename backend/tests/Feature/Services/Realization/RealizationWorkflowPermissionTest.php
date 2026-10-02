<?php

namespace Tests\Feature\Services\Realization;

use App\Services\Realization\Workflow\RealizationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class RealizationWorkflowPermissionTest extends TestCase
{
    use CreatesRealizationFixtures;
    use RefreshDatabase;

    public function test_setiap_permission_di_registry_ada_dan_dipegang_minimal_satu_role(): void
    {
        $this->seedRolesAndPermissions();

        foreach ((new RealizationWorkflow())->permissions() as $name) {
            // findByName melempar exception bila permission tidak ada di seeder.
            $permission = Permission::findByName($name, 'web');

            $this->assertGreaterThan(
                0,
                $permission->roles()->count(),
                "Permission {$name} ada tetapi tidak dipegang role mana pun."
            );
        }
    }
}