<?php

namespace App\Services\Formula;

/**
 * Hasil kalkulasi formula — immutable value object.
 *
 * achievement_pct/deviation bersifat nullable secara sengaja: formula
 * seperti nilai_langsung secara domain tidak menghasilkan basis capaian/
 * deviasi yang valid (lihat Owner Decision §15), jadi null di sini bukan
 * indikasi error, melainkan hasil yang memang tidak berlaku untuk formula
 * tersebut.
 */
final class FormulaResult
{
    public function __construct(
        public readonly ?float $achievementPct,
        public readonly ?float $deviation,
    ) {
    }
}