<?php

namespace App\Services\Formula\Calculators;

use App\Services\Formula\Contracts\FormulaCalculatorInterface;
use App\Services\Formula\FormulaResult;

/**
 * Realisasi dicatat langsung tanpa perhitungan formula (Owner Decision
 * §15) — achievement_pct dan deviation sengaja null, bukan bug.
 */
class NilaiLangsungCalculator implements FormulaCalculatorInterface
{
    public function calculate(float $targetValue, float $realizationValue): FormulaResult
    {
        return new FormulaResult(
            achievementPct: null,
            deviation: null,
        );
    }
}