<?php

namespace App\Services\Formula\Expression;

final class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
    ) {
    }
}