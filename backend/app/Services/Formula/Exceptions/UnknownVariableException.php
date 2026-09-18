<?php

namespace App\Services\Formula\Exceptions;

/**
 * Defense-in-depth: dilempar ExpressionEvaluator jika VariableNode berisi
 * nama selain 'target'/'realisasi'. Secara normal tidak akan pernah
 * terjadi karena Tokenizer sudah membatasi whitelist — exception ini
 * menjaga evaluator tetap ketat seandainya AST kelak berasal dari sumber
 * lain selain ExpressionParser.
 */
class UnknownVariableException extends FormulaCalculationException
{
}