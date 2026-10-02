<?php

namespace App\Enums;

/**
 * Status siklus hidup Realization. Nilai identik dengan yang dipersist di
 * kolom realizations.status (string biasa tanpa constraint DB — validitas
 * dijaga di level aplikasi lewat enum ini).
 */
enum RealizationStatus: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case DivalidasiKasubbid = 'divalidasi_kasubbid';
    case DivalidasiKabid = 'divalidasi_kabid';
    case DirekapSekretaris = 'direkap_sekretaris';
    case Dikembalikan = 'dikembalikan';
    case Disahkan = 'disahkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Diajukan',
            self::DivalidasiKasubbid => 'Divalidasi Kepala Sub Bidang',
            self::DivalidasiKabid => 'Divalidasi Kepala Bidang',
            self::DirekapSekretaris => 'Direkomendasikan Sekretaris, menunggu pengesahan Kepala Dinas',
            self::Dikembalikan => 'Dikembalikan untuk koreksi',
            self::Disahkan => 'Disahkan',
        };
    }

    /** Hanya Kepala Dinas yang dapat menghasilkan status final. */
    public function isFinal(): bool
    {
        return $this === self::Disahkan;
    }

    /** Status di mana pemilik data boleh mengubah nilai/menambah bukti. */
    public function isOwnerEditable(): bool
    {
        return in_array($this, [self::Draft, self::Dikembalikan], true);
    }
}