<?php

namespace Tests\Unit\Services\Formula;

use App\Services\Formula\Exceptions\DivisionByZeroFormulaException;
use App\Services\Formula\Exceptions\InvalidExpressionSyntaxException;
use App\Services\Formula\Expression\ExpressionEvaluator;
use App\Services\Formula\Expression\ExpressionParser;
use App\Services\Formula\Expression\Tokenizer;
use App\Services\Formula\SafeExpressionEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeExpressionEngineTest extends TestCase
{
    private SafeExpressionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SafeExpressionEngine(
            new Tokenizer(),
            new ExpressionParser(),
            new ExpressionEvaluator(),
        );
    }

    /**
     * @dataProvider validExpressionProvider
     */
    #[DataProvider('validExpressionProvider')]
    public function test_evaluates_valid_expressions_end_to_end(string $expression, float $target, float $realisasi, float $expected): void
    {
        $this->assertSame($expected, $this->engine->evaluate($expression, $target, $realisasi));
    }

    public static function validExpressionProvider(): array
    {
        return [
            'persentase capaian style' => ['(realisasi / target) * 100', 100.0, 80.0, 80.0],
            'selisih mentah' => ['target - realisasi', 100.0, 80.0, 20.0],
            'contoh owner CR' => ['(target - realisasi) / target * 100', 100.0, 80.0, 20.0],
            'variabel tunggal' => ['realisasi', 0.0, 42.0, 42.0],
            'unary minus' => ['-target + realisasi', 5.0, 10.0, 5.0],
            'desimal' => ['target * 0.75 + realisasi', 100.0, 5.0, 80.0],
        ];
    }

    public function test_rejects_function_call_syntax(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->engine->evaluate('abs(target)', 1.0, 1.0);
    }

    public function test_rejects_object_access_syntax(): void
    {
        $this->expectException(InvalidExpressionSyntaxException::class);
        $this->engine->evaluate('$user->value', 1.0, 1.0);
    }

    public function test_end_to_end_division_by_zero_throws_domain_exception(): void
    {
        $this->expectException(DivisionByZeroFormulaException::class);
        $this->engine->evaluate('target / 0', 100.0, 0.0);
    }
}