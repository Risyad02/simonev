<?php

namespace Tests\Unit\Services\Formula\Expression;

use App\Services\Formula\Exceptions\DivisionByZeroFormulaException;
use App\Services\Formula\Exceptions\UnknownVariableException;
use App\Services\Formula\Expression\ExpressionEvaluator;
use App\Services\Formula\Expression\Nodes\BinaryOpNode;
use App\Services\Formula\Expression\Nodes\NumberNode;
use App\Services\Formula\Expression\Nodes\VariableNode;
use PHPUnit\Framework\TestCase;

class ExpressionEvaluatorTest extends TestCase
{
    private ExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ExpressionEvaluator();
    }

    public function test_division_by_zero_throws_before_dividing(): void
    {
        $this->expectException(DivisionByZeroFormulaException::class);

        $node = new BinaryOpNode('/', new VariableNode('target'), new NumberNode(0.0));
        $this->evaluator->evaluate($node, target: 100, realisasi: 0);
    }

    public function test_division_by_negative_zero_throws(): void
    {
        $this->expectException(DivisionByZeroFormulaException::class);

        $node = new BinaryOpNode('/', new VariableNode('target'), new NumberNode(-0.0));
        $this->evaluator->evaluate($node, target: 100, realisasi: 0);
    }

    public function test_division_by_zero_via_computed_denominator(): void
    {
        // target / (target - target) -- denominator dihitung dulu, hasilnya 0
        $this->expectException(DivisionByZeroFormulaException::class);

        $denominator = new BinaryOpNode('-', new VariableNode('target'), new VariableNode('target'));
        $node = new BinaryOpNode('/', new VariableNode('target'), $denominator);
        $this->evaluator->evaluate($node, target: 50, realisasi: 0);
    }

    public function test_unknown_variable_defense_in_depth(): void
    {
        // ExpressionParser tidak akan pernah menghasilkan node ini secara
        // normal (Tokenizer sudah membatasi whitelist) — AST dikonstruksi
        // manual di sini untuk membuktikan evaluator tetap ketat sebagai
        // lapisan pertahanan kedua, bukan mengasumsikan input selalu aman.
        $this->expectException(UnknownVariableException::class);

        $node = new VariableNode('foobar');
        $this->evaluator->evaluate($node, target: 1, realisasi: 1);
    }

    public function test_valid_division_returns_correct_result(): void
    {
        $node = new BinaryOpNode('/', new VariableNode('realisasi'), new VariableNode('target'));
        $result = $this->evaluator->evaluate($node, target: 4, realisasi: 20);

        $this->assertSame(5.0, $result);
    }
}