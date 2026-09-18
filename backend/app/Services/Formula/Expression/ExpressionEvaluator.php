<?php

namespace App\Services\Formula\Expression;

use App\Services\Formula\Exceptions\DivisionByZeroFormulaException;
use App\Services\Formula\Exceptions\UnknownVariableException;
use App\Services\Formula\Expression\Nodes\BinaryOpNode;
use App\Services\Formula\Expression\Nodes\ExpressionNode;
use App\Services\Formula\Expression\Nodes\NumberNode;
use App\Services\Formula\Expression\Nodes\UnaryMinusNode;
use App\Services\Formula\Expression\Nodes\VariableNode;
use RuntimeException;

class ExpressionEvaluator
{
    public function evaluate(ExpressionNode $node, float $target, float $realisasi): float
    {
        if ($node instanceof NumberNode) {
            return $node->value;
        }

        if ($node instanceof VariableNode) {
            return match ($node->name) {
                'target' => $target,
                'realisasi' => $realisasi,
                default => throw new UnknownVariableException(
                    "Variable tidak dikenal: '{$node->name}'."
                ),
            };
        }

        if ($node instanceof UnaryMinusNode) {
            return -$this->evaluate($node->operand, $target, $realisasi);
        }

        if ($node instanceof BinaryOpNode) {
            $left = $this->evaluate($node->left, $target, $realisasi);
            $right = $this->evaluate($node->right, $target, $realisasi);

            return match ($node->operator) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $this->divide($left, $right),
                default => throw new RuntimeException(
                    "Operator tidak dikenal pada AST: '{$node->operator}'. Seharusnya tidak pernah terjadi — parser hanya menghasilkan +,-,*,/."
                ),
            };
        }

        throw new RuntimeException(
            'Jenis AST node tidak dikenal: '.get_class($node).'. Seharusnya tidak pernah terjadi — grammar tertutup hanya menghasilkan 4 jenis node.'
        );
    }

    private function divide(float $left, float $right): float
    {
        if ($right === 0.0 || $right === -0.0) {
            throw new DivisionByZeroFormulaException(
                'Pembagian dengan nol terdeteksi saat evaluasi expression.'
            );
        }

        return $left / $right;
    }
}