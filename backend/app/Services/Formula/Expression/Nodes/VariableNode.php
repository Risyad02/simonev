<?php

namespace App\Services\Formula\Expression\Nodes;

final class VariableNode implements ExpressionNode
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}