<?php

namespace App\Services\Formula\Expression;

use App\Services\Formula\Exceptions\InvalidExpressionSyntaxException;
use App\Services\Formula\Expression\Nodes\BinaryOpNode;
use App\Services\Formula\Expression\Nodes\ExpressionNode;
use App\Services\Formula\Expression\Nodes\NumberNode;
use App\Services\Formula\Expression\Nodes\UnaryMinusNode;
use App\Services\Formula\Expression\Nodes\VariableNode;

/**
 * Recursive-descent parser.
 *
 *   expression := term (("+" | "-") term)* EOF
 *   term       := factor (("*" | "/") factor)*
 *   factor     := primary | "-" factor
 *   primary    := NUMBER | VARIABLE | "(" expression ")"
 */
class ExpressionParser
{
    public const MAX_NESTING_DEPTH = 50;

    /** @var Token[] */
    private array $tokens = [];
    private int $position = 0;
    private int $depth = 0;

    /**
     * @param  Token[]  $tokens
     */
    public function parse(array $tokens): ExpressionNode
    {
        $this->tokens = $tokens;
        $this->position = 0;
        $this->depth = 0;

        if ($this->current()->type === TokenType::EOF) {
            throw new InvalidExpressionSyntaxException('Expression tidak boleh kosong.');
        }

        $node = $this->parseExpression();

        if ($this->current()->type !== TokenType::EOF) {
            throw new InvalidExpressionSyntaxException(
                "Token tidak terduga setelah expression selesai: '{$this->current()->value}'."
            );
        }

        return $node;
    }

    private function parseExpression(): ExpressionNode
    {
        $node = $this->parseTerm();

        while (in_array($this->current()->type, [TokenType::PLUS, TokenType::MINUS], true)) {
            $operator = $this->current()->type === TokenType::PLUS ? '+' : '-';
            $this->advance();
            $node = new BinaryOpNode($operator, $node, $this->parseTerm());
        }

        return $node;
    }

    private function parseTerm(): ExpressionNode
    {
        $node = $this->parseFactor();

        while (in_array($this->current()->type, [TokenType::STAR, TokenType::SLASH], true)) {
            $operator = $this->current()->type === TokenType::STAR ? '*' : '/';
            $this->advance();
            $node = new BinaryOpNode($operator, $node, $this->parseFactor());
        }

        return $node;
    }

    private function parseFactor(): ExpressionNode
    {
        if ($this->current()->type === TokenType::MINUS) {
            $this->advance();
            $this->enterNesting();
            $operand = $this->parseFactor();
            $this->exitNesting();

            return new UnaryMinusNode($operand);
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): ExpressionNode
    {
        $token = $this->current();

        if ($token->type === TokenType::NUMBER) {
            $this->advance();

            return new NumberNode((float) $token->value);
        }

        if ($token->type === TokenType::VARIABLE) {
            $this->advance();

            return new VariableNode($token->value);
        }

        if ($token->type === TokenType::LPAREN) {
            $this->advance();
            $this->enterNesting();
            $node = $this->parseExpression();
            $this->exitNesting();

            if ($this->current()->type !== TokenType::RPAREN) {
                throw new InvalidExpressionSyntaxException("Tanda kurung tutup ')' tidak ditemukan.");
            }

            $this->advance();

            return $node;
        }

        if ($token->type === TokenType::EOF) {
            throw new InvalidExpressionSyntaxException('Expression berakhir tidak lengkap, operand diharapkan.');
        }

        throw new InvalidExpressionSyntaxException("Token tidak terduga: '{$token->value}'.");
    }

    private function enterNesting(): void
    {
        $this->depth++;

        if ($this->depth > self::MAX_NESTING_DEPTH) {
            throw new InvalidExpressionSyntaxException(
                'Expression melebihi batas maksimum kedalaman nesting ('.self::MAX_NESTING_DEPTH.' level).'
            );
        }
    }

    private function exitNesting(): void
    {
        $this->depth--;
    }

    private function current(): Token
    {
        return $this->tokens[$this->position];
    }

    private function advance(): void
    {
        if ($this->position < count($this->tokens) - 1) {
            $this->position++;
        }
    }
}