<?php

namespace App\Services\Formula\Exceptions;

use RuntimeException;

/**
 * Base exception domain untuk seluruh kegagalan tokenize/parse/evaluate
 * pada Formula Engine. Caller (nanti RealizationService, Checkpoint 4+)
 * dapat catch ini secara generik atau salah satu turunannya secara
 * spesifik.
 */
abstract class FormulaCalculationException extends RuntimeException
{
}