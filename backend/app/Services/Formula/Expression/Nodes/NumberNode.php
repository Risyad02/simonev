<?php

namespace App\Services\Formula\Expression\Nodes;

final class NumberNode implements ExpressionNode
{
    public function __construct(
        public readonly float $value,
    ) {
    }
}