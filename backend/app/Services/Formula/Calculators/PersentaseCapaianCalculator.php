<?php

namespace App\Services\Formula\Calculators;

use App\Services\Formula\Contracts\FormulaCalculatorInterface;
use App\Services\Formula\Exceptions\DivisionByZeroFormulaException;
use App\Services\Formula\FormulaResult;

/**
 * achievement_pct = (realisasi / target) * 100
 * deviation       = achievement_pct - 100
 */
class PersentaseCapaianCalculator implements FormulaCalculatorInterface
{
    public function calculate(float $targetValue, float $realizationValue): FormulaResult
    {
        if ($targetValue === 0.0 || $targetValue === -0.0) {
            throw new DivisionByZeroFormulaException(
                'Pembagian dengan nol terdeteksi: target bernilai 0 pada perhitungan persentase_capaian.'
            );
        }

        $achievementPct = ($realizationValue / $targetValue) * 100;

        return new FormulaResult(
            achievementPct: $achievementPct,
            deviation: $achievementPct - 100,
        );
    }
}