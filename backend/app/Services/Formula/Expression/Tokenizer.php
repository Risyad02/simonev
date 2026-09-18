<?php

namespace App\Services\Formula\Expression;

use App\Services\Formula\Exceptions\InvalidExpressionSyntaxException;

/**
 * Security boundary pertama. Hanya karakter/pola yang eksplisit dikenali
 * di sini yang bisa menghasilkan token — tidak ada blacklist string,
 * murni whitelist struktural. Identifier apa pun selain 'target'/
 * 'realisasi' ditolak di sini, sebelum parser sempat melihatnya.
 */
class Tokenizer
{
    public const MAX_EXPRESSION_LENGTH = 500;

    private const ALLOWED_VARIABLES = ['target', 'realisasi'];

    private string $expression;
    private int $position = 0;
    private int $length = 0;

    /**
     * @return Token[]
     */
    public function tokenize(string $expression): array
    {
        if (mb_strlen($expression) > self::MAX_EXPRESSION_LENGTH) {
            throw new InvalidExpressionSyntaxException(
                'Expression melebihi batas maksimum '.self::MAX_EXPRESSION_LENGTH.' karakter.'
            );
        }

        $this->expression = $expression;
        $this->position = 0;
        $this->length = mb_strlen($expression);

        $tokens = [];

        while ($this->position < $this->length) {
            $char = $this->currentChar();

            if ($this->isWhitespace($char)) {
                $this->position++;
                continue;
            }

            $simple = match ($char) {
                '+' => TokenType::PLUS,
                '-' => TokenType::MINUS,
                '*' => TokenType::STAR,
                '/' => TokenType::SLASH,
                '(' => TokenType::LPAREN,
                ')' => TokenType::RPAREN,
                default => null,
            };

            if ($simple !== null) {
                $tokens[] = new Token($simple, $char);
                $this->position++;
                continue;
            }

            if ($this->isDigit($char)) {
                $tokens[] = $this->readNumber();
                continue;
            }

            if ($this->isAlpha($char)) {
                $tokens[] = $this->readVariable();
                continue;
            }

            throw new InvalidExpressionSyntaxException(
                "Karakter tidak dikenal pada posisi {$this->position}: '{$char}'."
            );
        }

        $tokens[] = new Token(TokenType::EOF, '');

        return $tokens;
    }

    private function readNumber(): Token
    {
        $start = $this->position;

        while ($this->position < $this->length && $this->isDigit($this->currentChar())) {
            $this->position++;
        }

        if ($this->position < $this->length && $this->currentChar() === '.') {
            $this->position++;

            if ($this->position >= $this->length || ! $this->isDigit($this->currentChar())) {
                throw new InvalidExpressionSyntaxException(
                    "Format angka tidak valid pada posisi {$start}: memerlukan digit setelah titik desimal."
                );
            }

            while ($this->position < $this->length && $this->isDigit($this->currentChar())) {
                $this->position++;
            }
        }

        $raw = mb_substr($this->expression, $start, $this->position - $start);

        return new Token(TokenType::NUMBER, $raw);
    }

    private function readVariable(): Token
    {
        $start = $this->position;

        while ($this->position < $this->length && $this->isAlpha($this->currentChar())) {
            $this->position++;
        }

        $identifier = mb_substr($this->expression, $start, $this->position - $start);

        if (! in_array($identifier, self::ALLOWED_VARIABLES, true)) {
            throw new InvalidExpressionSyntaxException(
                "Identifier tidak dikenal: '{$identifier}'. Hanya 'target' dan 'realisasi' yang diizinkan."
            );
        }

        return new Token(TokenType::VARIABLE, $identifier);
    }

    private function currentChar(): string
    {
        return mb_substr($this->expression, $this->position, 1);
    }

    private function isWhitespace(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r";
    }

    private function isDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    private function isAlpha(string $char): bool
    {
        return ($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z');
    }
}