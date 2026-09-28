<?php

namespace Tests\Unit\Services\Formula\Expression;

use App\Services\Formula\Exceptions\InvalidExpressionSyntaxException;
use App\Services\Formula\Expression\ExpressionEvaluator;
use App\Services\Formula\Expression\ExpressionParser;
use App\Services\Formula\Expression\Nodes\BinaryOpNode;
use App\Services\Formula\Expression\Nodes\UnaryMinusNode;
use App\Services\Formula\Expression\Tokenizer;
use PHPUnit\Framework\TestCase;

class ExpressionParserTest extends TestCase
{
    private Tokenizer $tokenizer;
    private ExpressionParser $parser;
    private ExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenizer = new Tokenizer();
        $this->parser = new ExpressionParser();
        $this->evaluator = new ExpressionEvaluator();
    }

    private function parse(string $expression)
    {
        return $this->parser->parse($this->tokenizer->tokenize($expression));
    }

    public function test_star_has_higher_precedence_than_plus(): void
    {
        // target + realisasi * 2  ==  target + (realisasi * 2)
        $ast = $this->parse('target + realisasi * 2');
        $result = $this->evaluator->evaluate($ast, target: 10, realisasi: 5);

        $this->assertSame(20.0, $result); // 10 + (5*2), bukan (10+5)*2=30
    }

    public function test_parentheses_override_precedence(): void
    {
        $ast = $this->parse('(target + realisasi) * 2');
        $result = $this->evaluator->evaluate($ast, target: 10, realisasi: 5);

        $this->assertSame(30.0, $result);
    }

    public function test_minus_is_left_associative(): void
    {
        // target - realisasi - 10 == (target - realisasi) - 10
        $ast = $this->parse('target - realisasi - 10');
        $result = $this->evaluator->evaluate($ast, target: 100, realisasi: 20);

        $this->assertSame(70.0, $result); // (100-20)-10=70, bukan 100-(20-10)=90
    }

    public function test_division_is_left_associative(): void
    {
        $ast = $this->parse('target / realisasi / 2');
        $result = $this->evaluator->evaluate($ast, target: 100, realisasi: 5);

        $this->assertSame(10.0, $result); // (100/5)/2=10, bukan 100/(5/2)=40
    }

    public function test_unary_minus_on_variable(): void
    {
        $ast = $this->parse('-target');
        $this->assertInstanceOf(UnaryMinusNode::class, $ast);

        $result = $this->evaluator->evaluate($ast, target: 5, realisasi: 0);
        $this->assertSame(-5.0, $result);
    }

    public function test_unary_minus_combined_with_binary(): void
    {
        $ast = $this->parse('-target + realisasi');
        $result = $this->evaluator->evaluate($ast, target: 5, realisasi: 10);

        $this->assertSame(5.0, $result);
    }

    public function test_nested_parentheses(): void
    {
        $ast = $this->parse('(((target)))');
        $result = $this->evaluator->evaluate($ast, target: 7, realisasi: 0);

        $this->assertSame(7.0, $result);
    }

    public function test_single_variable_is_valid_expression(): void
    {
        $ast = $this->parse('realisasi');
        $this->assertSame(42.0, $this->evaluator->evaluate($ast, target: 0, realisasi: 42));
    }

    public function test_rejects_trailing_operator(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->parse('target +');
    }

    public function test_rejects_unclosed_parenthesis(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->parse('(target + realisasi');
    }

    public function test_rejects_unexpected_closing_parenthesis(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->parse('target + realisasi)');
    }

    public function test_rejects_trailing_garbage_after_valid_expression(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->parse('target + realisasi garbage');
    }

    public function test_rejects_empty_expression(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->parse('');
    }

    public function test_rejects_whitespace_only_expression(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->parse('   ');
    }

    public function test_rejects_expression_exceeding_max_nesting_depth(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $deep = str_repeat('(', ExpressionParser::MAX_NESTING_DEPTH + 1).'target'.str_repeat(')', ExpressionParser::MAX_NESTING_DEPTH + 1);
        $this->parse($deep);
    }

    public function test_division_by_zero_is_syntax_valid_but_evaluation_fails_elsewhere(): void
    {
        // Parsing 'target / 0' harus SUKSES (syntax valid) — kegagalan
        // baru terjadi di evaluator, bukan di parser. Lihat
        // ExpressionEvaluatorTest untuk assertion exception-nya.
        $ast = $this->parse('target / 0');
        $this->assertInstanceOf(BinaryOpNode::class, $ast);
    }
}