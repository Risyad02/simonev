<?php

namespace App\Services\Formula\Exceptions;

/**
 * Dilempar oleh Tokenizer atau ExpressionParser: karakter/identifier di
 * luar whitelist, angka malformed, operand hilang, kurung tidak
 * seimbang, token tersisa setelah expression selesai, expression
 * kosong, atau melebihi batas panjang/kedalaman nesting.
 */
class InvalidExpressionSyntaxException extends FormulaCalculationException
{
}