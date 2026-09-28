<?php

namespace App\Services\Formula\Expression\Nodes;

final class UnaryMinusNode implements ExpressionNode
{
    public function __construct(
        public readonly ExpressionNode $operand,
    ) {
    }
}