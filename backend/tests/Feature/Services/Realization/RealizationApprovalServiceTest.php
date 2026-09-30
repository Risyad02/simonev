<?php

namespace Tests\Feature\Services\Realization;

use App\Enums\ApprovalHistoryAction as H;
use App\Enums\RealizationStatus as S;
use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\Realization;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Realization\RealizationApprovalService;
use App\Services\Realization\Workflow\WorkflowAction as A;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesRealizationFixtures;
use Tests\TestCase;

class RealizationApprovalServiceTest extends TestCase
{
    use CreatesRealizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function service(): RealizationApprovalService
    {
        return app(RealizationApprovalService::class);
    }

    private function makeRealization(User $owner, S $status = S::Draft): Realization
    {
        return Realization::factory()->withStatus($status)->ownedBy($owner)->create([
            'target_id'         => $this->createActiveTargetWithFormula()->id,
            'realization_value' => 80,
            'achievement_pct'   => 80,
            'deviation'         => -20,
        ]);
    }

    /**
     * Ditulis independen dari registry: [from, action, to, role aktor, history action, audit action, catatan wajib].
     */
    public static function transitions(): array
    {
        return [
            'T1' => [S::Draft, A::Submit, S::Diajukan, 'operator', H::Submit, 'realization_submit', false],
            'T2' => [S::Dikembalikan, A::Submit, S::Diajukan, 'operator', H::Submit, 'realization_resubmit', false],
            'T3' => [S::Diajukan, A::Approve, S::DivalidasiKasubbid, 'kepala_sub_bidang', H::Validate, 'realization_validate_kasubbid', false],
            'T4' => [S::DivalidasiKasubbid, A::Approve, S::DivalidasiKabid, 'kepala_bidang', H::Validate, 'realization_validate_kabid', false],
            'T5' => [S::DivalidasiKabid, A::Approve, S::DirekapSekretaris, 'sekretaris', H::Recap, 'realization_recommend_sekretaris', false],
            'T6' => [S::DirekapSekretaris, A::Approve, S::Disahkan, 'kepala_dinas', H::Approve, 'realization_finalize_kadis', false],
            'R1' => [S::Diajukan, A::SendBack, S::Dikembalikan, 'kepala_sub_bidang', H::Reject, 'realization_return_kasubbid', true],
            'R2' => [S::DivalidasiKasubbid, A::SendBack, S::Dikembalikan, 'kepala_bidang', H::Reject, 'realization_return_kabid', true],
            'R3' => [S::DivalidasiKabid, A::SendBack, S::Dikembalikan, 'sekretaris', H::Koreksi, 'realization_return_sekretaris', true],
            'R4' => [S::DirekapSekretaris, A::SendBack, S::Dikembalikan, 'kepala_dinas', H::Reject, 'realization_return_kadis', true],
        ];
    }

    public static function statusTidakBisaDiajukan(): array
    {
        return [
            'diajukan'            => [S::Diajukan],
            'divalidasi_kasubbid' => [S::DivalidasiKasubbid],
            'divalidasi_kabid'    => [S::DivalidasiKabid],
            'direkap_sekretaris'  => [S::DirekapSekretaris],
            'disahkan'            => [S::Disahkan],
        ];
    }

    #[DataProvider('transitions')]
    public function test_setiap_transisi_registry_berhasil_pada_level_service(
        S $from,
        A $action,
        S $to,
        string $role,
        H $history,
        string $audit,
        bool $noteRequired,
    ): void {
        $owner = $this->createUserWithRole('operator');
        $actor = $role === 'operator' ? $owner : $this->createUserWithRole($role);
        $realization = $this->makeRealization($owner, $from);
        $note = $noteRequired ? 'Perlu perbaikan data pendukung.' : null;

        [$ok, $result, $code] = $this->service()->perform($realization, $action, $actor, $note);

        $this->assertTrue($ok, is_string($result) ? $result : '');
        $this->assertNull($code);
        $this->assertSame($to->value, $result->status);
        $this->assertSame($to->value, $realization->fresh()->status);

        $this->assertSame(1, ApprovalHistory::count());
        $this->assertDatabaseHas('approval_history', [
            'realization_id' => $realization->id,
            'actor_id'       => $actor->id,
            'from_status'    => $from->value,
            'to_status'      => $to->value,
            'action'         => $history->value,
            'note'           => $note,
        ]);

        $this->assertSame(1, AuditLog::count());
        $log = AuditLog::firstOrFail();
        $this->assertSame($audit, $log->action);
        $this->assertSame('Realization', $log->entity_type);
        $this->assertSame($realization->id, (int) $log->entity_id);
        $this->assertSame($actor->id, (int) $log->user_id);
        $this->assertSame(['status' => $from->value], $log->old_value);
        $this->assertSame($to->value, $log->new_value['status']);
    }

    public function test_audit_transisi_menyimpan_snapshot_nilai_dan_owner_id_untuk_non_pemilik(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, S::Diajukan);

        $this->service()->perform($realization, A::Approve, $kasubbid);

        $log = AuditLog::firstOrFail();
        $this->assertEquals(80.0, (float) $log->new_value['realization_value']);
        $this->assertEquals(80.0, (float) $log->new_value['achievement_pct']);
        $this->assertEquals(-20.0, (float) $log->new_value['deviation']);
        $this->assertSame($owner->id, $log->new_value['owner_id']);
    }

    public function test_submit_oleh_pemilik_tidak_mencatat_owner_id_dan_bukan_backup(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);

        $this->service()->submit($realization, $owner);

        $log = AuditLog::firstOrFail();
        $this->assertSame('realization_submit', $log->action);
        $this->assertArrayNotHasKey('owner_id', $log->new_value);
    }

    public function test_backup_admin_dapat_submit_realisasi_operator_dengan_jejak_backup_dan_owner_id(): void
    {
        $owner = $this->createUserWithRole('operator');
        $admin = $this->createUserWithRole('admin');
        $realization = $this->makeRealization($owner);

        [$ok] = $this->service()->submit($realization, $admin);

        $this->assertTrue($ok);
        $log = AuditLog::firstOrFail();
        $this->assertSame('backup_realization_submit', $log->action);
        $this->assertSame($owner->id, $log->new_value['owner_id']);
        $this->assertDatabaseHas('approval_history', [
            'realization_id' => $realization->id,
            'actor_id'       => $admin->id,
            'action'         => 'submit',
        ]);
    }

    public function test_operator_bukan_pemilik_ditolak_403_tanpa_efek_samping(): void
    {
        $owner = $this->createUserWithRole('operator');
        $other = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);

        [$ok, , $code] = $this->service()->submit($realization, $other);

        $this->assertFalse($ok);
        $this->assertSame(403, $code);
        $this->assertSame('draft', $realization->fresh()->status);
        $this->assertSame(0, ApprovalHistory::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_pemilik_tidak_boleh_approve_atau_return_realisasinya_sendiri(): void
    {
        $owner = $this->createUserWithRole('operator');
        $owner->assignRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, S::Diajukan);

        [$ok1, , $code1] = $this->service()->perform($realization, A::Approve, $owner);
        [$ok2, , $code2] = $this->service()->perform($realization, A::SendBack, $owner, 'catatan');

        $this->assertFalse($ok1);
        $this->assertSame(403, $code1);
        $this->assertFalse($ok2);
        $this->assertSame(403, $code2);
        $this->assertSame('diajukan', $realization->fresh()->status);
        $this->assertSame(0, ApprovalHistory::count());
        $this->assertSame(0, AuditLog::count());
    }

    #[DataProvider('statusTidakBisaDiajukan')]
    public function test_submit_pada_status_selain_draft_dan_dikembalikan_ditolak_409(S $status): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner, $status);

        [$ok, , $code] = $this->service()->submit($realization, $owner);

        $this->assertFalse($ok);
        $this->assertSame(409, $code);
        $this->assertSame($status->value, $realization->fresh()->status);
        $this->assertSame(0, ApprovalHistory::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_status_tidak_dikenal_ditolak_409(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = Realization::factory()->ownedBy($owner)->create([
            'target_id' => $this->createActiveTargetWithFormula()->id,
            'status'    => 'status_lama_tak_dikenal',
        ]);

        [$ok, , $code] = $this->service()->submit($realization, $owner);

        $this->assertFalse($ok);
        $this->assertSame(409, $code);
        $this->assertSame('status_lama_tak_dikenal', $realization->fresh()->status);
    }

    public function test_approver_pada_tahap_yang_salah_ditolak_409(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, S::DivalidasiKasubbid);

        [$ok, , $code] = $this->service()->perform($realization, A::Approve, $kasubbid);

        $this->assertFalse($ok);
        $this->assertSame(409, $code);
        $this->assertSame('divalidasi_kasubbid', $realization->fresh()->status);
        $this->assertSame(0, ApprovalHistory::count());
    }

    public function test_approve_dan_return_pada_draft_atau_dikembalikan_ditolak_409(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');

        foreach ([S::Draft, S::Dikembalikan] as $status) {
            $realization = $this->makeRealization($owner, $status);

            [$okA, , $codeA] = $this->service()->perform($realization, A::Approve, $kasubbid);
            [$okR, , $codeR] = $this->service()->perform($realization, A::SendBack, $kasubbid, 'catatan');

            $this->assertFalse($okA);
            $this->assertSame(409, $codeA);
            $this->assertFalse($okR);
            $this->assertSame(409, $codeR);
            $this->assertSame($status->value, $realization->fresh()->status);
        }

        $this->assertSame(0, ApprovalHistory::count());
    }

    public function test_return_tanpa_catatan_atau_catatan_kosong_ditolak_422(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner, S::Diajukan);

        foreach ([null, '', '   '] as $note) {
            [$ok, , $code] = $this->service()->perform($realization, A::SendBack, $kasubbid, $note);

            $this->assertFalse($ok);
            $this->assertSame(422, $code);
        }

        $this->assertSame('diajukan', $realization->fresh()->status);
        $this->assertSame(0, ApprovalHistory::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_catatan_di_trim_sebelum_disimpan(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);

        $this->service()->submit($realization, $owner, '  Bukti sudah lengkap.  ');

        $this->assertDatabaseHas('approval_history', [
            'realization_id' => $realization->id,
            'note'           => 'Bukti sudah lengkap.',
        ]);
    }

    public function test_transisi_ganda_pada_record_basi_ditolak_409_tanpa_efek_samping(): void
    {
        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner); // objek ini akan basi setelah panggilan pertama

        [$ok1] = $this->service()->submit($realization, $owner);
        $this->assertTrue($ok1);
        $this->assertSame('draft', $realization->status); // objek di memori tidak ikut berubah

        [$ok2, , $code2] = $this->service()->submit($realization, $owner);

        $this->assertFalse($ok2);
        $this->assertSame(409, $code2);
        $this->assertSame(1, ApprovalHistory::count());
        $this->assertSame(1, AuditLog::count());
        $this->assertSame('diajukan', $realization->fresh()->status);
    }

    public function test_kegagalan_audit_membatalkan_perubahan_status_dan_history(): void
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
        $realization = $this->makeRealization($owner);

        try {
            $this->service()->submit($realization, $owner);
            $this->fail('RuntimeException diharapkan.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->assertSame('draft', $realization->fresh()->status);
        $this->assertSame(0, ApprovalHistory::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_kegagalan_history_membatalkan_perubahan_status_dan_audit(): void
    {
        ApprovalHistory::creating(function () {
            throw new RuntimeException('history gagal');
        });

        $owner = $this->createUserWithRole('operator');
        $realization = $this->makeRealization($owner);

        try {
            $this->service()->submit($realization, $owner);
            $this->fail('RuntimeException diharapkan.');
        } catch (RuntimeException) {
            // diharapkan
        } finally {
            $this->app['events']->forget('eloquent.creating: '.ApprovalHistory::class);
        }

        $this->assertSame('draft', $realization->fresh()->status);
        $this->assertSame(0, ApprovalHistory::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_siklus_submit_return_resubmit_menambah_history_tanpa_menghapus_riwayat_lama(): void
    {
        $owner = $this->createUserWithRole('operator');
        $kasubbid = $this->createUserWithRole('kepala_sub_bidang');
        $realization = $this->makeRealization($owner);

        $this->service()->submit($realization, $owner);
        $this->service()->perform($realization, A::SendBack, $kasubbid, 'Data belum lengkap.');
        $this->service()->submit($realization, $owner);

        $history = ApprovalHistory::where('realization_id', $realization->id)->orderBy('id')->get();

        $this->assertSame(['submit', 'reject', 'submit'], $history->pluck('action')->all());
        $this->assertSame(['draft', 'diajukan', 'dikembalikan'], $history->pluck('from_status')->all());
        $this->assertSame(['diajukan', 'dikembalikan', 'diajukan'], $history->pluck('to_status')->all());
        $this->assertSame(
            ['realization_submit', 'realization_return_kasubbid', 'realization_resubmit'],
            AuditLog::orderBy('id')->pluck('action')->all()
        );
        $this->assertSame('diajukan', $realization->fresh()->status);
    }
}