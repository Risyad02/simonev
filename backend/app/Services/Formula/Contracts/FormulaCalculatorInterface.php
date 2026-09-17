<?php

namespace App\Services\Formula\Contracts;

use App\Services\Formula\FormulaResult;

interface FormulaCalculatorInterface
{
    /**
     * Menghitung achievement_pct dan deviation dari satu pasang nilai
     * target-realisasi. Implementasi WAJIB melempar exception domain yang
     * eksplisit untuk kondisi tidak terdefinisi (mis. division by zero),
     * tidak boleh mengembalikan 0/null/INF/NaN secara diam-diam (Owner
     * Decision §14) — jenis exception-nya ditentukan di Checkpoint 4
     * bersamaan dengan calculator konkretnya.
     */
    public function calculate(float $targetValue, float $realizationValue): FormulaResult;
}