<?php

namespace Tests\Feature\Services\Dashboard;

use App\Services\Dashboard\DashboardScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class DashboardScopeServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesRealizationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function scopes(): DashboardScopeService
    {
        return app(DashboardScopeService::class);
    }

    public function test_each_role_resolves_to_its_expected_scope(): void
    {
        // role => [mode, readScoped, rowLevel, coverageAvailable, includeDataQuality, transitional]
        $expected = [
            'super_admin'       => ['full', false, true, true, true, false],
            'kepala_dinas'      => ['full', false, true, true, true, false],
            'sekretaris'        => ['cross-unit', false, true, true, true, false],
            'admin'             => ['operational', false, true, true, true, false],
            'operator'          => ['own-scope', true, true, false, false, true],
            'kepala_sub_bidang' => ['own-scope', true, false, false, false, true],
            'kepala_bidang'     => ['own-scope', true, false, false, false, true],
            'pimpinan'          => ['strategic-summary', false, false, true, false, false],
        ];

        foreach ($expected as $role => $want) {
            $scope = $this->scopes()->resolve($this->createUserWithRole($role));

            $this->assertNotNull($scope, "Role {$role} harus punya scope dashboard.");
            $this->assertSame($want, [
                $scope->mode,
                $scope->readScoped,
                $scope->rowLevel,
                $scope->coverageAvailable,
                $scope->includeDataQuality,
                $scope->transitional,
            ], "Scope tidak sesuai untuk role {$role}.");
        }
    }

    public function test_role_without_dashboard_permission_has_no_scope(): void
    {
        $this->assertNull($this->scopes()->resolve($this->createUserWithRole('publik')));
    }

    public function test_broadest_permission_wins_when_user_holds_several(): void
    {
        $operator = $this->createUserWithRole('operator');
        $operator->givePermissionTo('dashboard.view.strategic-summary');
        $this->assertSame('own-scope', $this->scopes()->resolve($operator)->mode);

        $operator->givePermissionTo('dashboard.view.operational');
        $this->assertSame('operational', $this->scopes()->resolve($operator)->mode);

        $operator->givePermissionTo('dashboard.view.cross-unit');
        $this->assertSame('cross-unit', $this->scopes()->resolve($operator)->mode);

        $operator->givePermissionTo('dashboard.view.full');
        $this->assertSame('full', $this->scopes()->resolve($operator)->mode);
    }
}