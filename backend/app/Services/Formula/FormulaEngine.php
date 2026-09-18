<?php

namespace App\Services\Formula;

use App\Models\Formula;
use App\Services\Formula\Calculators\NilaiLangsungCalculator;
use App\Services\Formula\Calculators\PersentaseCapaianCalculator;
use App\Services\Formula\Calculators\TargetPerRealisasiCalculator;
use App\Services\Formula\Exceptions\UnsupportedFormulaTypeException;

/**
 * Satu entry point kalkulasi formula, akan dipakai RealizationService
 * (belum dibuat — Realization Core checkpoint terpisah). Menentukan
 * jalur eksekusi berdasarkan Formula.type: system -> Calculator
 * strategy, custom -> SafeExpressionEngine. Tidak ada silent fallback:
 * formula_type belum diimplementasikan (akumulasi/rata_rata/bobot) atau
 * type tidak dikenal keduanya melempar UnsupportedFormulaTypeException.
 */
class FormulaEngine
{
    public function __construct(
        private readonly SafeExpressionEngine $safeExpressionEngine,
    ) {
    }

    public function evaluate(Formula $formula, float $targetValue, float $realizationValue): FormulaResult
    {
        return match ($formula->type) {
            'system' => $this->evaluateSystemFormula($formula, $targetValue, $realizationValue),
            'custom' => $this->evaluateCustomFormula($formula, $targetValue, $realizationValue),
            default => throw new UnsupportedFormulaTypeException(
                "Formula type tidak dikenal: '{$formula->type}'."
            ),
        };
    }

    private function evaluateSystemFormula(Formula $formula, float $targetValue, float $realizationValue): FormulaResult
    {
        $calculator = match ($formula->formula_type) {
            'persentase_capaian' => new PersentaseCapaianCalculator(),
            'target_per_realisasi' => new TargetPerRealisasiCalculator(),
            'nilai_langsung' => new NilaiLangsungCalculator(),
            default => throw new UnsupportedFormulaTypeException(
                "Formula type '{$formula->formula_type}' belum diimplementasikan pada Phase 10."
            ),
        };

        return $calculator->calculate($targetValue, $realizationValue);
    }

    private function evaluateCustomFormula(Formula $formula, float $targetValue, float $realizationValue): FormulaResult
    {
        $achievementPct = $this->safeExpressionEngine->evaluate(
            $formula->expression,
            $targetValue,
            $realizationValue,
        );

        return new FormulaResult(
            achievementPct: $achievementPct,
            deviation: null,
        );
    }
}