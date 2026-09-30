<?php

namespace Tests\Unit\Realization;

use App\Enums\RealizationStatus;
use PHPUnit\Framework\TestCase;

class RealizationStatusTest extends TestCase
{
    public function test_memiliki_tepat_tujuh_status_sesuai_nilai_di_database(): void
    {
        $values = array_map(fn (RealizationStatus $s) => $s->value, RealizationStatus::cases());

        $this->assertCount(7, $values);
        $this->assertEqualsCanonicalizing([
            'draft',
            'diajukan',
            'divalidasi_kasubbid',
            'divalidasi_kabid',
            'direkap_sekretaris',
            'dikembalikan',
            'disahkan',
        ], $values);
    }

    public function test_hanya_disahkan_yang_final(): void
    {
        foreach (RealizationStatus::cases() as $status) {
            $this->assertSame(
                $status === RealizationStatus::Disahkan,
                $status->isFinal(),
                "isFinal() salah untuk {$status->value}"
            );
        }
    }

    public function test_hanya_draft_dan_dikembalikan_yang_dapat_diedit_pemilik(): void
    {
        foreach (RealizationStatus::cases() as $status) {
            $expected = in_array($status, [RealizationStatus::Draft, RealizationStatus::Dikembalikan], true);

            $this->assertSame($expected, $status->isOwnerEditable(), "isOwnerEditable() salah untuk {$status->value}");
        }
    }

    public function test_label_direkap_sekretaris_jelas_bukan_final(): void
    {
        $direkap = RealizationStatus::DirekapSekretaris->label();

        $this->assertStringContainsString('menunggu pengesahan', $direkap);
        $this->assertNotSame(RealizationStatus::Disahkan->label(), $direkap);
    }

    public function test_setiap_label_terisi_dan_unik(): void
    {
        $labels = array_map(fn (RealizationStatus $s) => $s->label(), RealizationStatus::cases());

        foreach ($labels as $label) {
            $this->assertNotSame('', $label);
        }
        $this->assertCount(count($labels), array_unique($labels));
    }

    public function test_tryfrom_menolak_nilai_tidak_dikenal(): void
    {
        $this->assertSame(RealizationStatus::Draft, RealizationStatus::tryFrom('draft'));
        $this->assertNull(RealizationStatus::tryFrom('bukan_status'));
    }
}