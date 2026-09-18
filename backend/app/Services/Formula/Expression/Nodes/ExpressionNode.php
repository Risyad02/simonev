<?php

namespace App\Services\Formula\Expression\Nodes;

/**
 * Marker interface. Hanya 4 kelas berikut yang boleh mengimplementasikan
 * ini: NumberNode, VariableNode, BinaryOpNode, UnaryMinusNode — grammar
 * tertutup, ExpressionEvaluator mengasumsikan tidak ada jenis node lain
 * yang mungkin dihasilkan ExpressionParser.
 */
interface ExpressionNode
{
}