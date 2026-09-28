<?php

namespace App\Services\Formula\Calculators;

use App\Services\Formula\Contracts\FormulaCalculatorInterface;
use App\Services\Formula\Exceptions\DivisionByZeroFormulaException;
use App\Services\Formula\FormulaResult;

/**
 * achievement_pct = (target / realisasi) * 100
 * deviation       = achievement_pct - 100
 */
class TargetPerRealisasiCalculator implements FormulaCalculatorInterface
{
    public function calculate(float $targetValue, float $realizationValue): FormulaResult
    {
        if ($realizationValue === 0.0 || $realizationValue === -0.0) {
            throw new DivisionByZeroFormulaException(
                'Pembagian dengan nol terdeteksi: realisasi bernilai 0 pada perhitungan target_per_realisasi.'
            );
        }

        $achievementPct = ($targetValue / $realizationValue) * 100;

        return new FormulaResult(
            achievementPct: $achievementPct,
            deviation: $achievementPct - 100,
        );
    }
}