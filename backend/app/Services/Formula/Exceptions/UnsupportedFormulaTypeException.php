<?php

namespace App\Services\Formula\Exceptions;

/**
 * Dilempar FormulaEngine ketika:
 * - Formula.type === 'system' tapi formula_type belum punya calculator
 *   terimplementasi (akumulasi, rata_rata, bobot — Owner Decision §5)
 * - Formula.type tidak dikenal sama sekali (bukan 'system'/'custom')
 *
 * Tidak ada silent fallback — kondisi tak terdukung selalu eksplisit.
 */
class UnsupportedFormulaTypeException extends FormulaCalculationException
{
}