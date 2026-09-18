<?php

namespace App\Services\Formula\Expression\Nodes;

final class BinaryOpNode implements ExpressionNode
{
    public function __construct(
        public readonly string $operator,
        public readonly ExpressionNode $left,
        public readonly ExpressionNode $right,
    ) {
    }
}