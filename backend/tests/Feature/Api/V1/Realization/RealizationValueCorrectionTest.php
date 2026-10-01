<?php

namespace Tests\Feature\Api\V1\Realization;

use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\Realization;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class RealizationValueCorrectionTest extends TestCase
{
    use CreatesRealizationFixtures;
    use RefreshDatabase;

    private const REASON = 'Koreksi sesuai data pendukung terbaru.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function makeRealization(User $owner, string $status, string $formulaType = 'persentase_capaian'): Realization
    {
        $direct = $formulaType === 'nilai_langsung';

        return Realization::factory()->ownedBy($owner)->create([
            'target_id'         => $this->createActiveTargetWithFormula($formulaType)->id,
            'status'            => $status,
            'realization_value' => 80,
            'achievement_pct'   => $direct ? null : 80,
            'deviation'         => $direct ? null : -20,
        ]);
    }

    private function url(Realization $realization): string
    {
        return "/api/v1/realizations/{$realization->id}/value";
    }

    private function valueAudits()
    {
        return AuditLog::whereIn('action', ['realization_value_correct', 'backup_realization_value_correct'])
            ->orderBy('id')
            ->get();
    }

    public static function statusTidakBisaDikoreksi(): array
    {
        return [
            'draft'               => ['draft'],
            'diajukan'            => ['diajukan'],
            'divalidasi_kasubbid' => ['divalidasi_kasubbid'],
            'divalidasi_kabid'    => ['divalidasi_kabid'],
            'direkap_sekretaris'  => ['direkap_sekretaris'],
            'disahkan'            => ['disahkan'],
        ];
    }

    public function test_pemilik_dapat_mengoreksi_nilai_saat_dikembalikan_dengan_audit_before_after(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'dikembalikan');
        Sanctum::actingAs($owner, ['*']);

        $response = $this->patchJson($this->url($realization), [
            'realization_value' => 90,
            'reason'            => self::REASON,
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'dikembalikan')
            ->assertJsonPath('data.is_final', false);

        $this->assertEquals(90.0, (float) $response->json('data.realization_value'));
        $this->assertEquals(90.0, (float) $response->json('data.achievement_pct'));
        $this->assertEquals(-10.0, (float) $response->json('data.deviation'));

        $fresh = $realization->fresh();
        $this->assertSame('dikembalikan', $fresh->status);
        $this->assertEquals(90.0, (float) $fresh->realization_value);
        $this->assertSame($owner->id, (int) $fresh->input_by);

        $this->assertSame(0, ApprovalHistory::count()); // koreksi bukan transisi status

        $audits = $this->valueAudits();
        $this->assertCount(1, $audits);
        $log = $audits[0];
        $this->assertSame('realization_value_correct', $log->action);
        $this->assertSame('Realization', $log->entity_type);
        $this->assertSame($realization->id, (int) $log->entity_id);
        $this->assertSame($owner->id, (int) $log->user_id);
        $this->assertEquals(80.0, (float) $log->old_value['realization_value']);
        $this->assertEquals(80.0, (float) $log->old_value['achievement_pct']);
        $this->assertEquals(-20.0, (float) $log->old_value['deviation']);
        $this->assertEquals(90.0, (float) $log->new_value['realization_value']);
        $this->assertEquals(90.0, (float) $log->new_value['achievement_pct']);
        $this->assertEquals(-10.0, (float) $log->new_value['deviation']);
        $this->assertSame(self::REASON, $log->new_value['reason']);
        $this->assertArrayNotHasKey('owner_id', $log->new_value);
    }

    #[DataProvider('statusTidakBisaDikoreksi')]
    public function test_koreksi_pada_status_selain_dikembalikan_ditolak_409(string $status): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, $status);
        Sanctum::actingAs($owner, ['*']);

        $this->patchJson($this->url($realization), [
            'realization_value' => 90,
            'reason'            => self::REASON,
        ])->assertStatus(409);

        $this->assertEquals(80.0, (float) $realization->fresh()->realization_value);
        $this->assertSame($status, $realization->fresh()->status);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_bukan_pemilik_dan_role_tanpa_permission_manage_ditolak_403(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'dikembalikan');

        $actors = [
            'operator lain' => $this->createUserWithRole('operator'),
            'kabid'         => $this->createUserWithRole('kepala_bidang'),
            'sekretaris'    => $this->createUserWithRole('sekretaris'),
            'pimpinan'      => $this->createUserWithRole('pimpinan'),
        ];

        foreach ($actors as $label => $actor) {
            Sanctum::actingAs($actor, ['*']);

            $this->patchJson($this->url($realization), [
                'realization_value' => 90,
                'reason'            => self::REASON,
            ])->assertStatus(403, "Harus 403 untuk {$label}");
        }

        $this->assertEquals(80.0, (float) $realization->fresh()->realization_value);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_tanpa_autentikasi_ditolak_401(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'dikembalikan');

        $this->patchJson($this->url($realization), [
            'realization_value' => 90,
            'reason'            => self::REASON,
        ])->assertStatus(401);

        $this->assertEquals(80.0, (float) $realization->fresh()->realization_value);
    }

    public function test_admin_backup_dapat_mengoreksi_dengan_jejak_backup_dan_owner_id(): void
    {
        $owner = $this->createUserWithRole('operator');
        $admin = $this->createUserWithRole('admin');
        $realization = $this->makeRealization($owner, 'dikembalikan');
        Sanctum::actingAs($admin, ['*']);

        $this->patchJson($this->url($realization), [
            'realization_value' => 70,
            'reason'            => self::REASON,
        ])->assertStatus(200);

        $log = $this->valueAudits()->firstOrFail();
        $this->assertSame('backup_realization_value_correct', $log->action);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame($owner->id, $log->new_value['owner_id']);
        $this->assertSame($owner->id, (int) $realization->fresh()->input_by);
    }

    public function test_validasi_nilai_dan_alasan_menghasilkan_422(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'dikembalikan');
        Sanctum::actingAs($owner, ['*']);

        $invalid = [
            'tanpa alasan'   => ['realization_value' => 90],
            'alasan kosong'  => ['realization_value' => 90, 'reason' => '   '],
            'alasan pendek'  => ['realization_value' => 90, 'reason' => 'ok'],
            'tanpa nilai'    => ['reason' => self::REASON],
            'nilai bukan angka' => ['realization_value' => 'abc', 'reason' => self::REASON],
        ];

        foreach ($invalid as $label => $body) {
            $this->patchJson($this->url($realization), $body)->assertStatus(422, "Harus 422 untuk {$label}");
        }

        $this->assertEquals(80.0, (float) $realization->fresh()->realization_value);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_field_di_luar_nilai_dan_alasan_diabaikan(): void
    {
        $owner = $this->createUserWithRole('operator');
        $other = User::factory()->create();
        $realization = $this->makeRealization($owner, 'dikembalikan');
        $originalTargetId = $realization->target_id;
        Sanctum::actingAs($owner, ['*']);

        $this->patchJson($this->url($realization), [
            'realization_value' => 90,
            'reason'            => self::REASON,
            'status'            => 'disahkan',
            'input_by'          => $other->id,
            'achievement_pct'   => 999,
            'deviation'         => 999,
            'target_id'         => 999999,
        ])->assertStatus(200)->assertJsonPath('data.status', 'dikembalikan');

        $fresh = $realization->fresh();
        $this->assertSame('dikembalikan', $fresh->status);
        $this->assertSame($owner->id, (int) $fresh->input_by);
        $this->assertSame($originalTargetId, $fresh->target_id);
        $this->assertEquals(90.0, (float) $fresh->achievement_pct);
        $this->assertEquals(-10.0, (float) $fresh->deviation);
    }

    public function test_koreksi_tetap_memakai_target_asli_walau_target_sudah_nonaktif(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'dikembalikan');
        $realization->target()->update(['is_active' => false]);
        Sanctum::actingAs($owner, ['*']);

        $response = $this->patchJson($this->url($realization), [
            'realization_value' => 50,
            'reason'            => self::REASON,
        ])->assertStatus(200);

        $this->assertEquals(50.0, (float) $response->json('data.achievement_pct')); // 50 / 100 target asli
        $this->assertSame($realization->target_id, $realization->fresh()->target_id);
    }

    public function test_division_by_zero_pada_formula_dikembalikan_422_tanpa_perubahan(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'dikembalikan');
        $realization->target()->update(['target_value' => 0]);
        Sanctum::actingAs($owner, ['*']);

        $this->patchJson($this->url($realization), [
            'realization_value' => 50,
            'reason'            => self::REASON,
        ])->assertStatus(422);

        $this->assertEquals(80.0, (float) $realization->fresh()->realization_value);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_formula_nilai_langsung_menghasilkan_achievement_dan_deviation_null(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'dikembalikan', 'nilai_langsung');
        Sanctum::actingAs($owner, ['*']);

        $response = $this->patchJson($this->url($realization), [
            'realization_value' => 70,
            'reason'            => self::REASON,
        ])->assertStatus(200);

        $this->assertEquals(70.0, (float) $response->json('data.realization_value'));
        $this->assertNull($response->json('data.achievement_pct'));
        $this->assertNull($response->json('data.deviation'));
    }

    public function test_koreksi_berulang_membentuk_rantai_audit_tanpa_menimpa_riwayat(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'dikembalikan');
        Sanctum::actingAs($owner, ['*']);

        $this->patchJson($this->url($realization), ['realization_value' => 90, 'reason' => self::REASON])->assertStatus(200);
        $this->patchJson($this->url($realization), ['realization_value' => 95, 'reason' => 'Perbaikan kedua dari data.'])->assertStatus(200);

        $audits = $this->valueAudits();

        $this->assertCount(2, $audits);
        $this->assertEquals(80.0, (float) $audits[0]->old_value['realization_value']);
        $this->assertEquals(90.0, (float) $audits[0]->new_value['realization_value']);
        $this->assertEquals(90.0, (float) $audits[1]->old_value['realization_value']);
        $this->assertEquals(95.0, (float) $audits[1]->new_value['realization_value']);
        $this->assertEquals(95.0, (float) $realization->fresh()->realization_value);
    }

    public function test_siklus_return_koreksi_resubmit_menyimpan_nilai_terkoreksi_pada_snapshot_validasi(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, 'draft');
        $base = "/api/v1/realizations/{$realization->id}";

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("{$base}/submit")->assertStatus(200);

        Sanctum::actingAs($kasubbid, ['*']);
        $this->postJson("{$base}/return", ['note' => 'Nilai perlu diperiksa ulang.'])->assertStatus(200);

        Sanctum::actingAs($owner, ['*']);
        $this->patchJson("{$base}/value", ['realization_value' => 90, 'reason' => self::REASON])->assertStatus(200);
        $this->postJson("{$base}/submit")->assertStatus(200);

        Sanctum::actingAs($kasubbid, ['*']);
        $this->postJson("{$base}/approve")->assertStatus(200);

        $this->assertSame(
            ['submit', 'reject', 'submit', 'validate'],
            ApprovalHistory::where('realization_id', $realization->id)->orderBy('id')->pluck('action')->all()
        );
        $this->assertSame(
            [
                'realization_submit',
                'realization_return_kasubbid',
                'realization_value_correct',
                'realization_resubmit',
                'realization_validate_kasubbid',
            ],
            AuditLog::orderBy('id')->pluck('action')->all()
        );

        // Validasi Kasubbid terikat pada nilai TERKOREKSI, bukan nilai awal.
        $validation = AuditLog::where('action', 'realization_validate_kasubbid')->firstOrFail();
        $this->assertEquals(90.0, (float) $validation->new_value['realization_value']);
        $this->assertEquals(90.0, (float) $validation->new_value['achievement_pct']);
    }

    public function test_realisasi_tidak_ditemukan_menghasilkan_404(): void
    {
        Sanctum::actingAs($this->createUserWithRole('operator'), ['*']);

        $this->patchJson('/api/v1/realizations/999999/value', [
            'realization_value' => 90,
            'reason'            => self::REASON,
        ])->assertStatus(404);
    }

    public function test_kegagalan_audit_membatalkan_perubahan_nilai(): void
    {
        $this->app->instance(AuditService::class, new class extends AuditService {
            public function log(
                string $action,
                string $entityType,
                int $entityId,
                ?array $oldValue,
                ?array $newValue,
                ?User $actor,
            ): AuditLog {
                throw new RuntimeException('audit gagal');
            }
        });

        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, 'dikembalikan');
        Sanctum::actingAs($owner, ['*']);

        $this->patchJson($this->url($realization), [
            'realization_value' => 90,
            'reason'            => self::REASON,
        ])->assertStatus(500);

        $fresh = $realization->fresh();
        $this->assertEquals(80.0, (float) $fresh->realization_value);
        $this->assertEquals(80.0, (float) $fresh->achievement_pct);
        $this->assertSame(0, AuditLog::count());
    }
}