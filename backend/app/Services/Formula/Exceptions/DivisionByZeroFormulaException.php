<?php

namespace App\Services\Formula\Exceptions;

/**
 * Dilempar ExpressionEvaluator ketika operand kanan operator '/'
 * bernilai 0.0 (termasuk -0.0). Dilempar SEBELUM operasi pembagian
 * dilakukan — PHP tidak pernah diberi kesempatan menghasilkan INF/NAN.
 */
class DivisionByZeroFormulaException extends FormulaCalculationException
{
}