<?php

namespace Tests\Unit\Realization;

use App\Enums\ApprovalHistoryAction as H;
use App\Enums\RealizationStatus as S;
use App\Services\Realization\Workflow\ActorRule;
use App\Services\Realization\Workflow\RealizationTransition;
use App\Services\Realization\Workflow\RealizationWorkflow;
use App\Services\Realization\Workflow\WorkflowAction as A;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RealizationWorkflowTest extends TestCase
{
    /**
     * Spesifikasi yang disetujui Owner, ditulis ulang secara independen dari
     * registry agar perubahan tidak sengaja pada registry langsung terdeteksi.
     */
    public static function expectedTransitions(): array
    {
        $manage = ['realization.manage', 'realization.manage.backup'];

        return [
            'T1' => ['T1', S::Draft, A::Submit, S::Diajukan, $manage, ActorRule::OwnerOrBackup, false, H::Submit, 'realization_submit'],
            'T2' => ['T2', S::Dikembalikan, A::Submit, S::Diajukan, $manage, ActorRule::OwnerOrBackup, false, H::Submit, 'realization_resubmit'],
            'T3' => ['T3', S::Diajukan, A::Approve, S::DivalidasiKasubbid, ['realization.validate.kasubbid'], ActorRule::NotOwner, false, H::Validate, 'realization_validate_kasubbid'],
            'T4' => ['T4', S::DivalidasiKasubbid, A::Approve, S::DivalidasiKabid, ['realization.validate.kabid'], ActorRule::NotOwner, false, H::Validate, 'realization_validate_kabid'],
            'T5' => ['T5', S::DivalidasiKabid, A::Approve, S::DirekapSekretaris, ['realization.recommend.sekretaris'], ActorRule::NotOwner, false, H::Recap, 'realization_recommend_sekretaris'],
            'T6' => ['T6', S::DirekapSekretaris, A::Approve, S::Disahkan, ['realization.finalize.kadis'], ActorRule::NotOwner, false, H::Approve, 'realization_finalize_kadis'],
            'R1' => ['R1', S::Diajukan, A::SendBack, S::Dikembalikan, ['realization.validate.kasubbid'], ActorRule::NotOwner, true, H::Reject, 'realization_return_kasubbid'],
            'R2' => ['R2', S::DivalidasiKasubbid, A::SendBack, S::Dikembalikan, ['realization.validate.kabid'], ActorRule::NotOwner, true, H::Reject, 'realization_return_kabid'],
            'R3' => ['R3', S::DivalidasiKabid, A::SendBack, S::Dikembalikan, ['realization.return.sekretaris'], ActorRule::NotOwner, true, H::Koreksi, 'realization_return_sekretaris'],
            'R4' => ['R4', S::DirekapSekretaris, A::SendBack, S::Dikembalikan, ['realization.finalize.kadis'], ActorRule::NotOwner, true, H::Reject, 'realization_return_kadis'],
        ];
    }

    public function test_registry_memiliki_tepat_sepuluh_transisi(): void
    {
        $this->assertCount(10, (new RealizationWorkflow())->all());
    }

    #[DataProvider('expectedTransitions')]
    public function test_transisi_sesuai_desain_final(
        string $code,
        S $from,
        A $action,
        S $to,
        array $permissions,
        ActorRule $rule,
        bool $noteRequired,
        H $history,
        string $audit,
    ): void {
        $transition = (new RealizationWorkflow())->find($from, $action);

        $this->assertNotNull($transition, "Transisi {$code} tidak ditemukan.");
        $this->assertSame($code, $transition->code);
        $this->assertSame($from, $transition->from);
        $this->assertSame($action, $transition->action);
        $this->assertSame($to, $transition->to);
        $this->assertEqualsCanonicalizing($permissions, $transition->permissions);
        $this->assertSame($rule, $transition->actorRule);
        $this->assertSame($noteRequired, $transition->noteRequired);
        $this->assertSame($history, $transition->historyAction);
        $this->assertSame($audit, $transition->auditAction);
    }

    public function test_kombinasi_status_dan_aksi_di_luar_sepuluh_transisi_tidak_valid(): void
    {
        $workflow = new RealizationWorkflow();

        $valid = [];
        foreach (self::expectedTransitions() as $row) {
            $valid[$row[1]->value.'|'.$row[2]->value] = true;
        }

        $checked = 0;
        foreach (S::cases() as $status) {
            foreach (A::cases() as $action) {
                $found = $workflow->find($status, $action);
                $key = $status->value.'|'.$action->value;

                if (isset($valid[$key])) {
                    $this->assertNotNull($found, "Seharusnya valid: {$key}");
                } else {
                    $this->assertNull($found, "Seharusnya tidak valid: {$key}");
                }
                $checked++;
            }
        }

        $this->assertSame(21, $checked);
    }

    public function test_disahkan_terminal_tanpa_transisi_keluar(): void
    {
        $workflow = new RealizationWorkflow();

        $this->assertSame([], $workflow->outgoing(S::Disahkan));

        foreach (A::cases() as $action) {
            $this->assertNull($workflow->find(S::Disahkan, $action));
        }
    }

    public function test_hanya_t6_yang_menghasilkan_status_final(): void
    {
        $toFinal = array_filter(
            (new RealizationWorkflow())->all(),
            fn (RealizationTransition $t) => $t->to->isFinal()
        );

        $this->assertCount(1, $toFinal);
        $this->assertSame('T6', array_values($toFinal)[0]->code);
        $this->assertSame(['realization.finalize.kadis'], array_values($toFinal)[0]->permissions);
    }

    public function test_approve_sekretaris_bukan_status_final(): void
    {
        $t5 = (new RealizationWorkflow())->find(S::DivalidasiKabid, A::Approve);

        $this->assertSame(S::DirekapSekretaris, $t5->to);
        $this->assertFalse($t5->to->isFinal());
    }

    public function test_semua_return_menuju_dikembalikan_dan_wajib_catatan(): void
    {
        $returns = array_filter(
            (new RealizationWorkflow())->all(),
            fn (RealizationTransition $t) => $t->action === A::SendBack
        );

        $this->assertCount(4, $returns);

        foreach ($returns as $t) {
            $this->assertSame(S::Dikembalikan, $t->to, "{$t->code} harus menuju dikembalikan");
            $this->assertTrue($t->noteRequired, "{$t->code} wajib catatan");
        }
    }

    public function test_transisi_selain_return_tidak_mewajibkan_catatan(): void
    {
        foreach ((new RealizationWorkflow())->all() as $t) {
            if ($t->action !== A::SendBack) {
                $this->assertFalse($t->noteRequired, "{$t->code} tidak boleh wajib catatan");
            }
        }
    }

    public function test_aturan_aktor_submit_pemilik_atau_backup_dan_sisanya_bukan_pemilik(): void
    {
        foreach ((new RealizationWorkflow())->all() as $t) {
            $expected = $t->action === A::Submit ? ActorRule::OwnerOrBackup : ActorRule::NotOwner;

            $this->assertSame($expected, $t->actorRule, "Aturan aktor salah pada {$t->code}");
        }
    }

    public function test_hanya_r3_memakai_history_koreksi(): void
    {
        $koreksi = array_filter(
            (new RealizationWorkflow())->all(),
            fn (RealizationTransition $t) => $t->historyAction === H::Koreksi
        );

        $this->assertCount(1, $koreksi);
        $this->assertSame('R3', array_values($koreksi)[0]->code);
    }

    public function test_rantai_approve_dari_draft_mencapai_disahkan_lewat_semua_tahap(): void
    {
        $workflow = new RealizationWorkflow();

        $status = $workflow->find(S::Draft, A::Submit)->to;
        $path = [$status];

        while (($next = $workflow->find($status, A::Approve)) !== null) {
            $status = $next->to;
            $path[] = $status;
        }

        $this->assertSame([
            S::Diajukan,
            S::DivalidasiKasubbid,
            S::DivalidasiKabid,
            S::DirekapSekretaris,
            S::Disahkan,
        ], $path);
    }

    public function test_draft_dan_dikembalikan_tidak_bisa_di_approve_atau_return(): void
    {
        $workflow = new RealizationWorkflow();

        foreach ([S::Draft, S::Dikembalikan] as $status) {
            $this->assertNull($workflow->find($status, A::Approve));
            $this->assertNull($workflow->find($status, A::SendBack));
        }
    }

    public function test_permission_unik_yang_dirujuk_registry(): void
    {
        $this->assertSame([
            'realization.finalize.kadis',
            'realization.manage',
            'realization.manage.backup',
            'realization.recommend.sekretaris',
            'realization.return.sekretaris',
            'realization.validate.kabid',
            'realization.validate.kasubbid',
        ], (new RealizationWorkflow())->permissions());
    }

    public function test_correct_sekretaris_tidak_dipakai_workflow(): void
    {
        $this->assertNotContains('realization.correct.sekretaris', (new RealizationWorkflow())->permissions());
    }

    public function test_registry_menolak_transisi_ganda(): void
    {
        $official = (new RealizationWorkflow())->all();

        $this->expectException(LogicException::class);

        new RealizationWorkflow([$official[0], $official[0]]);
    }

    public function test_registry_kustom_dapat_dipakai_tanpa_mengubah_pemanggil(): void
    {
        $official = (new RealizationWorkflow())->all();
        $custom = new RealizationWorkflow([$official[0]]);

        $this->assertCount(1, $custom->all());
        $this->assertNotNull($custom->find(S::Draft, A::Submit));
        $this->assertNull($custom->find(S::Diajukan, A::Approve));
    }
}