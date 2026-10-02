<?php

namespace Tests\Feature\Api\V1\Realization;

use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\Realization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class RealizationApprovalTest extends TestCase
{
    use CreatesRealizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function makeRealization(User $owner, string $status): Realization
    {
        return Realization::factory()->ownedBy($owner)->create([
            'target_id'         => $this->createActiveTargetWithFormula()->id,
            'status'            => $status,
            'realization_value' => 80,
            'achievement_pct'   => 80,
            'deviation'         => -20,
        ]);
    }

    private function url(Realization $realization, string $endpoint): string
    {
        return "/api/v1/realizations/{$realization->id}/{$endpoint}";
    }

    private function assertUnchanged(Realization $realization, string $status): void
    {
        $this->assertSame($status, $realization->fresh()->status);
        $this->assertSame(0, ApprovalHistory::count());
        $this->assertSame(0, AuditLog::count());
    }

    /**
     * [status awal, endpoint, role aktor, status tujuan, history action, audit action, kirim catatan, is_final]
     * Ditulis independen dari registry.
     */
    public static function httpTransitions(): array
    {
        return [
            'T3' => ['diajukan', 'approve', 'kepala_sub_bidang', 'divalidasi_kasubbid', 'validate', 'realization_validate_kasubbid', false, false],
            'T4' => ['divalidasi_kasubbid', 'approve', 'kepala_bidang', 'divalidasi_kabid', 'validate', 'realization_validate_kabid', false, false],
            'T5' => ['divalidasi_kabid', 'approve', 'sekretaris', 'direkap_sekretaris', 'recap', 'realization_recommend_sekretaris', false, false],
            'T6' => ['direkap_sekretaris', 'approve', 'kepala_dinas', 'disahkan', 'approve', 'realization_finalize_kadis', false, true],
            'R1' => ['diajukan', 'return', 'kepala_sub_bidang', 'dikembalikan', 'reject', 'realization_return_kasubbid', true, false],
            'R2' => ['divalidasi_kasubbid', 'return', 'kepala_bidang', 'dikembalikan', 'reject', 'realization_return_kabid', true, false],
            'R3' => ['divalidasi_kabid', 'return', 'sekretaris', 'dikembalikan', 'koreksi', 'realization_return_sekretaris', true, false],
            'R4' => ['direkap_sekretaris', 'return', 'kepala_dinas', 'dikembalikan', 'reject', 'realization_return_kadis', true, false],
        ];
    }

    /** [role aktor, status realisasi] — aktor memegang permission endpoint tetapi bukan pada tahapnya. */
    public static function tahapSalah(): array
    {
        return [
            'kasubbid pada tahap kabid'      => ['kepala_sub_bidang', 'divalidasi_kasubbid'],
            'kabid pada tahap kasubbid'      => ['kepala_bidang', 'diajukan'],
            'sekretaris pada tahap kasubbid' => ['sekretaris', 'diajukan'],
            'sekretaris pada tahap kadis'    => ['sekretaris', 'direkap_sekretaris'],
            'kadis pada tahap kabid'         => ['kepala_dinas', 'divalidasi_kabid'],
            'kadis pada tahap kasubbid'      => ['kepala_dinas', 'diajukan'],
        ];
    }

    /** [status realisasi, role approver] — tidak ada transisi approve/return dari status ini. */
    public static function statusTanpaTransisiApproval(): array
    {
        return [
            'draft'        => ['draft', 'kepala_sub_bidang'],
            'dikembalikan' => ['dikembalikan', 'kepala_bidang'],
            'disahkan'     => ['disahkan', 'kepala_dinas'],
        ];
    }

    #[DataProvider('httpTransitions')]
    public function test_transisi_via_http_berhasil_dan_tercatat(
        string $from,
        string $endpoint,
        string $role,
        string $to,
        string $historyAction,
        string $auditAction,
        bool $withNote,
        bool $isFinal,
    ): void {
        $owner = $this->createUserWithRole('operator');
        $actor = $this->createUserWithRole($role);
        $realization = $this->makeRealization($owner, $from);
        Sanctum::actingAs($actor, ['*']);

        $note = 'Data perlu dilengkapi.';
        $response = $this->postJson($this->url($realization, $endpoint), $withNote ? ['note' => $note] : []);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', $to)
            ->assertJsonPath('data.is_final', $isFinal);

        if ($to === 'direkap_sekretaris') {
            $this->assertStringContainsString('menunggu pengesahan', $response->json('data.status_label'));
        }

        $this->assertSame($to, $realization->fresh()->status);
        $this->assertSame(1, ApprovalHistory::count());
        $this->assertDatabaseHas('approval_history', [
            'realization_id' => $realization->id,
            'actor_id'       => $actor->id,
            'from_status'    => $from,
            'to_status'      => $to,
            'action'         => $historyAction,
            'note'           => $withNote ? $note : null,
        ]);
        $this->assertSame(1, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Realization',
            'entity_id'   => $realization->id,
            'action'      => $auditAction,
            'user_id'     => $actor->id,
        ]);
    }

    public function test_return_tanpa_catatan_yang_valid_ditolak_422(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, 'diajukan');
        Sanctum::actingAs($kasubbid, ['*']);

        foreach ([[], ['note' => ''], ['note' => '   '], ['note' => 'ok'], ['note' => ['bukan-string']]] as $body) {
            $this->postJson($this->url($realization, 'return'), $body)->assertStatus(422);
        }

        $this->assertUnchanged($realization, 'diajukan');
    }

    public function test_role_tanpa_permission_tahap_ditolak_403_pada_approve_dan_return(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'diajukan');

        $actors = [
            $owner,
            $this->createUserWithRole('operator'),
            $this->createUserWithRole('admin'),
            $this->createUserWithRole('pimpinan'),
        ];

        foreach ($actors as $actor) {
            Sanctum::actingAs($actor, ['*']);

            $this->postJson($this->url($realization, 'approve'))->assertStatus(403);
            $this->postJson($this->url($realization, 'return'), ['note' => 'Catatan pengembalian.'])->assertStatus(403);
        }

        $this->assertUnchanged($realization, 'diajukan');
    }

    public function test_tanpa_autentikasi_ditolak_401_pada_approve_dan_return(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'diajukan');

        $this->postJson($this->url($realization, 'approve'))->assertStatus(401);
        $this->postJson($this->url($realization, 'return'), ['note' => 'Catatan pengembalian.'])->assertStatus(401);

        $this->assertUnchanged($realization, 'diajukan');
    }

    #[DataProvider('tahapSalah')]
    public function test_aktor_pada_tahap_yang_salah_ditolak_409(string $role, string $status): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, $status);
        Sanctum::actingAs($this->createUserWithRole($role), ['*']);

        $this->postJson($this->url($realization, 'approve'))->assertStatus(409);
        $this->postJson($this->url($realization, 'return'), ['note' => 'Catatan pengembalian.'])->assertStatus(409);

        $this->assertUnchanged($realization, $status);
    }

    #[DataProvider('statusTanpaTransisiApproval')]
    public function test_status_tanpa_transisi_approval_ditolak_409(string $status, string $role): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, $status);
        Sanctum::actingAs($this->createUserWithRole($role), ['*']);

        $this->postJson($this->url($realization, 'approve'))->assertStatus(409);
        $this->postJson($this->url($realization, 'return'), ['note' => 'Catatan pengembalian.'])->assertStatus(409);

        $this->assertUnchanged($realization, $status);
    }

    public function test_pemilik_yang_juga_approver_ditolak_403_pada_approve_dan_return(): void
    {
        $owner = $this->createUserWithRole('operator');
        $owner->assignRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, 'diajukan');
        Sanctum::actingAs($owner, ['*']);

        $this->postJson($this->url($realization, 'approve'))->assertStatus(403);
        $this->postJson($this->url($realization, 'return'), ['note' => 'Catatan pengembalian.'])->assertStatus(403);

        $this->assertUnchanged($realization, 'diajukan');
    }

    public function test_field_workflow_di_body_approve_tidak_berpengaruh(): void
    {
        $owner = $this->createUserWithRole('operator');
        $other = User::factory()->create();
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, 'diajukan');
        Sanctum::actingAs($kasubbid, ['*']);

        $this->postJson($this->url($realization, 'approve'), [
            'status'      => 'disahkan',
            'from_status' => 'direkap_sekretaris',
            'to_status'   => 'disahkan',
            'action'      => 'approve',
            'actor_id'    => $other->id,
            'input_by'    => $other->id,
            'acted_at'    => '2000-01-01 00:00:00',
        ])->assertStatus(200)->assertJsonPath('data.status', 'divalidasi_kasubbid');

        $history = ApprovalHistory::where('realization_id', $realization->id)->firstOrFail();
        $this->assertSame($kasubbid->id, (int) $history->actor_id);
        $this->assertSame('diajukan', $history->from_status);
        $this->assertSame('divalidasi_kasubbid', $history->to_status);
        $this->assertSame('validate', $history->action);
        $this->assertTrue($history->acted_at->greaterThan(now()->subMinute()));
        $this->assertSame($owner->id, (int) $realization->fresh()->input_by);
    }

    public function test_field_workflow_di_body_return_tidak_berpengaruh(): void
    {
        $owner = $this->createUserWithRole('operator');
        $other = User::factory()->create();
        $kabid = $this->createUserWithRole('kepala_bidang');
        $realization = $this->makeRealization($owner, 'divalidasi_kasubbid');
        Sanctum::actingAs($kabid, ['*']);

        $this->postJson($this->url($realization, 'return'), [
            'note'        => 'Nilai realisasi perlu diperiksa ulang.',
            'status'      => 'disahkan',
            'from_status' => 'direkap_sekretaris',
            'to_status'   => 'disahkan',
            'action'      => 'approve',
            'actor_id'    => $other->id,
            'input_by'    => $other->id,
            'acted_at'    => '2000-01-01 00:00:00',
        ])->assertStatus(200)->assertJsonPath('data.status', 'dikembalikan');

        $history = ApprovalHistory::where('realization_id', $realization->id)->firstOrFail();
        $this->assertSame($kabid->id, (int) $history->actor_id);
        $this->assertSame('divalidasi_kasubbid', $history->from_status);
        $this->assertSame('dikembalikan', $history->to_status);
        $this->assertSame('reject', $history->action);
        $this->assertTrue($history->acted_at->greaterThan(now()->subMinute()));
        $this->assertSame($owner->id, (int) $realization->fresh()->input_by);
    }

    public function test_catatan_approve_opsional_tersimpan_di_history(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, 'diajukan');
        Sanctum::actingAs($kasubbid, ['*']);

        $this->postJson($this->url($realization, 'approve'), ['note' => 'Data sudah sesuai.'])
            ->assertStatus(200);

        $this->assertDatabaseHas('approval_history', [
            'realization_id' => $realization->id,
            'note'           => 'Data sudah sesuai.',
        ]);
    }

    public function test_catatan_approve_tidak_valid_ditolak_422(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, 'diajukan');
        Sanctum::actingAs($kasubbid, ['*']);

        $this->postJson($this->url($realization, 'approve'), ['note' => ['bukan-string']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $this->postJson($this->url($realization, 'approve'), ['note' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $this->assertUnchanged($realization, 'diajukan');
    }

    public function test_realisasi_tidak_ditemukan_menghasilkan_404(): void
    {
        Sanctum::actingAs($this->createUserWithRole('kepala_sub_bidang'), ['*']);

        $this->postJson('/api/v1/realizations/999999/approve')->assertStatus(404);
        $this->postJson('/api/v1/realizations/999999/return', ['note' => 'Catatan pengembalian.'])->assertStatus(404);
    }

    public function test_siklus_lengkap_via_http_menghasilkan_lima_history_berurutan_dan_disahkan(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $kabid = $this->createUserWithRole('kepala_bidang');
        $sekretaris = $this->createUserWithRole('sekretaris');
        $kadis = $this->createUserWithRole('kepala_dinas');
        $realization = $this->makeRealization($owner, 'draft');

        $steps = [
            [$owner, 'submit'],
            [$kasubbid, 'approve'],
            [$kabid, 'approve'],
            [$sekretaris, 'approve'],
            [$kadis, 'approve'],
        ];

        foreach ($steps as [$actor, $endpoint]) {
            Sanctum::actingAs($actor, ['*']);
            $this->postJson($this->url($realization, $endpoint))->assertStatus(200);
        }

        $fresh = $realization->fresh();
        $this->assertSame('disahkan', $fresh->status);
        $this->assertTrue($fresh->is_final);

        $history = ApprovalHistory::where('realization_id', $realization->id)->orderBy('id')->get();
        $this->assertSame(['submit', 'validate', 'validate', 'recap', 'approve'], $history->pluck('action')->all());
        $this->assertSame(
            ['diajukan', 'divalidasi_kasubbid', 'divalidasi_kabid', 'direkap_sekretaris', 'disahkan'],
            $history->pluck('to_status')->all()
        );
        $this->assertSame(
            [$owner->id, $kasubbid->id, $kabid->id, $sekretaris->id, $kadis->id],
            $history->pluck('actor_id')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertSame(
            [
                'realization_submit',
                'realization_validate_kasubbid',
                'realization_validate_kabid',
                'realization_recommend_sekretaris',
                'realization_finalize_kadis',
            ],
            AuditLog::orderBy('id')->pluck('action')->all()
        );

        // disahkan terminal: tidak ada aksi yang dapat mengubahnya lagi.
        Sanctum::actingAs($kadis, ['*']);
        $this->postJson($this->url($realization, 'approve'))->assertStatus(409);
        $this->postJson($this->url($realization, 'return'), ['note' => 'Catatan pengembalian.'])->assertStatus(409);
        Sanctum::actingAs($owner, ['*']);
        $this->postJson($this->url($realization, 'submit'))->assertStatus(409);

        $this->assertSame(5, ApprovalHistory::count());
        $this->assertSame('disahkan', $realization->fresh()->status);
    }

    public function test_siklus_koreksi_sekretaris_kembali_ke_pemilik_lalu_rantai_diulang_tanpa_menghapus_riwayat(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $kabid = $this->createUserWithRole('kepala_bidang');
        $sekretaris = $this->createUserWithRole('sekretaris');
        $kadis = $this->createUserWithRole('kepala_dinas');
        $realization = $this->makeRealization($owner, 'draft');

        $steps = [
            [$owner, 'submit', null],
            [$kasubbid, 'approve', null],
            [$kabid, 'approve', null],
            [$sekretaris, 'return', 'Nilai tidak konsisten dengan data pendukung.'],
            [$owner, 'submit', null],
            [$kasubbid, 'approve', null],
            [$kabid, 'approve', null],
            [$sekretaris, 'approve', null],
            [$kadis, 'approve', null],
        ];

        foreach ($steps as [$actor, $endpoint, $note]) {
            Sanctum::actingAs($actor, ['*']);
            $this->postJson($this->url($realization, $endpoint), $note === null ? [] : ['note' => $note])
                ->assertStatus(200);
        }

        $this->assertSame('disahkan', $realization->fresh()->status);

        $history = ApprovalHistory::where('realization_id', $realization->id)->orderBy('id')->get();
        $this->assertSame(
            ['submit', 'validate', 'validate', 'koreksi', 'submit', 'validate', 'validate', 'recap', 'approve'],
            $history->pluck('action')->all()
        );
        $this->assertSame('Nilai tidak konsisten dengan data pendukung.', $history[3]->note);
        $this->assertSame('dikembalikan', $history[3]->to_status);
        $this->assertSame(
            [
                'realization_submit',
                'realization_validate_kasubbid',
                'realization_validate_kabid',
                'realization_return_sekretaris',
                'realization_resubmit',
                'realization_validate_kasubbid',
                'realization_validate_kabid',
                'realization_recommend_sekretaris',
                'realization_finalize_kadis',
            ],
            AuditLog::orderBy('id')->pluck('action')->all()
        );
    }
}