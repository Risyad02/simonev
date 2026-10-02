<?php

namespace Tests\Feature\Api\V1\Realization;

use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\Realization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class RealizationSubmitTest extends TestCase
{
    use CreatesRealizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function makeRealization(User $owner, string $status = 'draft'): Realization
    {
        return Realization::factory()->ownedBy($owner)->create([
            'target_id'         => $this->createActiveTargetWithFormula()->id,
            'status'            => $status,
            'realization_value' => 80,
            'achievement_pct'   => 80,
            'deviation'         => -20,
        ]);
    }

    private function submitUrl(Realization $realization): string
    {
        return "/api/v1/realizations/{$realization->id}/submit";
    }

    public function test_operator_pemilik_dapat_mengajukan_realisasi_draft(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);
        Sanctum::actingAs($owner, ['*']);

        $this->postJson($this->submitUrl($realization))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'diajukan')
            ->assertJsonPath('data.is_final', false)
            ->assertJsonPath('data.status_label', 'Diajukan');

        $this->assertSame('diajukan', $realization->fresh()->status);
        $this->assertDatabaseHas('approval_history', [
            'realization_id' => $realization->id,
            'actor_id'       => $owner->id,
            'from_status'    => 'draft',
            'to_status'      => 'diajukan',
            'action'         => 'submit',
            'note'           => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Realization',
            'entity_id'   => $realization->id,
            'action'      => 'realization_submit',
            'user_id'     => $owner->id,
        ]);
    }

    public function test_pemilik_dapat_mengajukan_ulang_realisasi_yang_dikembalikan(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'dikembalikan');
        Sanctum::actingAs($owner, ['*']);

        $this->postJson($this->submitUrl($realization))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'diajukan');

        $this->assertDatabaseHas('approval_history', [
            'realization_id' => $realization->id,
            'from_status'    => 'dikembalikan',
            'to_status'      => 'diajukan',
            'action'         => 'submit',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $realization->id,
            'action'    => 'realization_resubmit',
        ]);
    }

    public function test_catatan_opsional_tersimpan_di_history(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);
        Sanctum::actingAs($owner, ['*']);

        $this->postJson($this->submitUrl($realization), ['note' => 'Bukti dukung lengkap.'])
            ->assertStatus(200);

        $this->assertDatabaseHas('approval_history', [
            'realization_id' => $realization->id,
            'note'           => 'Bukti dukung lengkap.',
        ]);
    }

    public function test_admin_backup_dapat_mengajukan_dan_tercatat_sebagai_backup(): void
    {
        $owner = $this->createUserWithRole('operator');
        $admin = $this->createUserWithRole('admin');
        $realization = $this->makeRealization($owner);
        Sanctum::actingAs($admin, ['*']);

        $this->postJson($this->submitUrl($realization))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'diajukan');

        $log = AuditLog::where('entity_id', $realization->id)->firstOrFail();
        $this->assertSame('backup_realization_submit', $log->action);
        $this->assertSame($owner->id, $log->new_value['owner_id']);
        $this->assertDatabaseHas('approval_history', [
            'realization_id' => $realization->id,
            'actor_id'       => $admin->id,
        ]);
    }

    public function test_operator_lain_bukan_pemilik_ditolak_403(): void
    {
        $owner = $this->createUserWithRole('operator');
        $other = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);
        Sanctum::actingAs($other, ['*']);

        $this->postJson($this->submitUrl($realization))->assertStatus(403);

        $this->assertSame('draft', $realization->fresh()->status);
        $this->assertSame(0, ApprovalHistory::count());
    }

    public function test_role_tanpa_permission_manage_ditolak_403(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);
        Sanctum::actingAs($this->createUserWithRole('kepala_bidang'), ['*']);

        $this->postJson($this->submitUrl($realization))->assertStatus(403);

        $this->assertSame('draft', $realization->fresh()->status);
    }

    public function test_tanpa_autentikasi_ditolak_401(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);

        $this->postJson($this->submitUrl($realization))->assertStatus(401);

        $this->assertSame('draft', $realization->fresh()->status);
    }

    public function test_submit_pada_status_yang_tidak_valid_ditolak_409(): void
    {
        $owner = $this->createUserWithRole('operator');
        Sanctum::actingAs($owner, ['*']);

        foreach (['diajukan', 'disahkan'] as $status) {
            $realization = $this->makeRealization($owner, $status);

            $this->postJson($this->submitUrl($realization))->assertStatus(409);

            $this->assertSame($status, $realization->fresh()->status);
        }

        $this->assertSame(0, ApprovalHistory::count());
    }

    public function test_pengajuan_kedua_pada_realisasi_yang_sama_ditolak_409(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);
        Sanctum::actingAs($owner, ['*']);

        $this->postJson($this->submitUrl($realization))->assertStatus(200);
        $this->postJson($this->submitUrl($realization))->assertStatus(409);

        $this->assertSame(1, ApprovalHistory::count());
    }

    public function test_status_actor_waktu_dan_field_workflow_di_body_tidak_berpengaruh(): void
    {
        $owner = $this->createUserWithRole('operator');
        $other = User::factory()->create();
        $realization = $this->makeRealization($owner);
        Sanctum::actingAs($owner, ['*']);

        $this->postJson($this->submitUrl($realization), [
            'status'      => 'disahkan',
            'from_status' => 'diajukan',
            'to_status'   => 'disahkan',
            'action'      => 'approve',
            'actor_id'    => $other->id,
            'input_by'    => $other->id,
            'acted_at'    => '2000-01-01 00:00:00',
        ])->assertStatus(200)->assertJsonPath('data.status', 'diajukan');

        $history = ApprovalHistory::where('realization_id', $realization->id)->firstOrFail();
        $this->assertSame($owner->id, (int) $history->actor_id);
        $this->assertSame('draft', $history->from_status);
        $this->assertSame('diajukan', $history->to_status);
        $this->assertSame('submit', $history->action);
        $this->assertTrue($history->acted_at->greaterThan(now()->subMinute()));
        $this->assertSame($owner->id, (int) $realization->fresh()->input_by);
    }

    public function test_catatan_tidak_valid_ditolak_422(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);
        Sanctum::actingAs($owner, ['*']);

        $this->postJson($this->submitUrl($realization), ['note' => ['bukan-string']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $this->postJson($this->submitUrl($realization), ['note' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $this->assertSame('draft', $realization->fresh()->status);
        $this->assertSame(0, ApprovalHistory::count());
    }

    public function test_realisasi_tidak_ditemukan_menghasilkan_404(): void
    {
        Sanctum::actingAs($this->createUserWithRole('operator'), ['*']);

        $this->postJson('/api/v1/realizations/999999/submit')->assertStatus(404);
    }
}