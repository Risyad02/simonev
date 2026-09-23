<?php

namespace Tests\Unit\Services\Formula\Expression;

use App\Services\Formula\Exceptions\InvalidExpressionSyntaxException;
use App\Services\Formula\Expression\Tokenizer;
use App\Services\Formula\Expression\TokenType;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{
    private Tokenizer $tokenizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenizer = new Tokenizer();
    }

    public function test_tokenizes_number_and_variable(): void
    {
        $tokens = $this->tokenizer->tokenize('target realisasi 10.5');

        $this->assertSame(TokenType::VARIABLE, $tokens[0]->type);
        $this->assertSame('target', $tokens[0]->value);
        $this->assertSame(TokenType::VARIABLE, $tokens[1]->type);
        $this->assertSame('realisasi', $tokens[1]->value);
        $this->assertSame(TokenType::NUMBER, $tokens[2]->type);
        $this->assertSame('10.5', $tokens[2]->value);
        $this->assertSame(TokenType::EOF, $tokens[3]->type);
    }

    public function test_tokenizes_all_operators_and_parens(): void
    {
        $tokens = $this->tokenizer->tokenize('+-*/()');

        $this->assertSame(
            [TokenType::PLUS, TokenType::MINUS, TokenType::STAR, TokenType::SLASH, TokenType::LPAREN, TokenType::RPAREN, TokenType::EOF],
            array_map(fn ($t) => $t->type, $tokens)
        );
    }

    public function test_whitespace_is_skipped_between_tokens(): void
    {
        $withSpace = $this->tokenizer->tokenize('target   +   realisasi');
        $noSpace = $this->tokenizer->tokenize('target+realisasi');

        $this->assertCount(count($noSpace), $withSpace);
    }

    public function test_rejects_unknown_identifier(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->tokenizer->tokenize('foobar * target');
    }

    public function test_rejects_uppercase_variable_case_sensitive(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->tokenizer->tokenize('Target');
    }

    public function test_rejects_dollar_sign(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->tokenizer->tokenize('$user');
    }

    public function test_rejects_malformed_decimal_leading_dot(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->tokenizer->tokenize('.5');
    }

    public function test_rejects_malformed_decimal_trailing_dot(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->tokenizer->tokenize('10.');
    }

    public function test_rejects_expression_exceeding_max_length(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->tokenizer->tokenize(str_repeat('1+', Tokenizer::MAX_EXPRESSION_LENGTH));
    }
}