<?php

namespace Tests\Unit\Services\Formula;

use App\Models\Formula;
use App\Services\Formula\Exceptions\DivisionByZeroFormulaException;
use App\Services\Formula\Exceptions\UnsupportedFormulaTypeException;
use App\Services\Formula\Expression\ExpressionEvaluator;
use App\Services\Formula\Expression\ExpressionParser;
use App\Services\Formula\Expression\Tokenizer;
use App\Services\Formula\FormulaEngine;
use App\Services\Formula\SafeExpressionEngine;
use PHPUnit\Framework\TestCase;

class FormulaEngineTest extends TestCase
{
    private FormulaEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FormulaEngine(
            new SafeExpressionEngine(new Tokenizer(), new ExpressionParser(), new ExpressionEvaluator())
        );
    }

    private function makeFormula(array $attributes): Formula
    {
        // Konstruksi murni in-memory, TIDAK disimpan ke DB -- FormulaEngine
        // hanya butuh objek Formula dengan atribut yang relevan.
        return new Formula($attributes);
    }

    public function test_resolves_persentase_capaian_system_formula(): void
    {
        $formula = $this->makeFormula(['type' => 'system', 'formula_type' => 'persentase_capaian']);
        $result = $this->engine->evaluate($formula, targetValue: 100, realizationValue: 80);

        $this->assertSame(80.0, $result->achievementPct);
        $this->assertSame(-20.0, $result->deviation);
    }

    public function test_resolves_target_per_realisasi_system_formula(): void
    {
        $formula = $this->makeFormula(['type' => 'system', 'formula_type' => 'target_per_realisasi']);
        $result = $this->engine->evaluate($formula, targetValue: 100, realizationValue: 50);

        $this->assertSame(200.0, $result->achievementPct);
    }

    public function test_resolves_nilai_langsung_system_formula(): void
    {
        $formula = $this->makeFormula(['type' => 'system', 'formula_type' => 'nilai_langsung']);
        $result = $this->engine->evaluate($formula, targetValue: 100, realizationValue: 80);

        $this->assertNull($result->achievementPct);
        $this->assertNull($result->deviation);
    }

    public function test_unimplemented_system_formula_type_throws(): void
    {
        $this->expectException(UnsupportedFormulaTypeException::class);

        $formula = $this->makeFormula(['type' => 'system', 'formula_type' => 'akumulasi']);
        $this->engine->evaluate($formula, targetValue: 100, realizationValue: 80);
    }

    public function test_bobot_and_rata_rata_also_throw(): void
    {
        foreach (['rata_rata', 'bobot'] as $unimplementedType) {
            $formula = $this->makeFormula(['type' => 'system', 'formula_type' => $unimplementedType]);

            try {
                $this->engine->evaluate($formula, targetValue: 100, realizationValue: 80);
                $this->fail("Expected UnsupportedFormulaTypeException for formula_type={$unimplementedType}");
            } catch (UnsupportedFormulaTypeException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_unknown_formula_type_column_throws(): void
    {
        $this->expectException(UnsupportedFormulaTypeException::class);

        $formula = $this->makeFormula(['type' => 'not_a_real_type', 'formula_type' => 'persentase_capaian']);
        $this->engine->evaluate($formula, targetValue: 100, realizationValue: 80);
    }

    public function test_resolves_custom_formula_via_expression(): void
    {
        $formula = $this->makeFormula(['type' => 'custom', 'expression' => 'target - realisasi']);
        $result = $this->engine->evaluate($formula, targetValue: 100, realizationValue: 30);

        $this->assertSame(70.0, $result->achievementPct);
        $this->assertNull($result->deviation); // Opsi B -- deviation selalu null untuk custom
    }

    public function test_custom_formula_with_null_expression_throws(): void
    {
        $this->expectException(UnsupportedFormulaTypeException::class);

        $formula = $this->makeFormula(['type' => 'custom', 'expression' => null]);
        $this->engine->evaluate($formula, targetValue: 100, realizationValue: 30);
    }

    public function test_custom_formula_with_empty_string_expression_throws(): void
    {
        $this->expectException(UnsupportedFormulaTypeException::class);

        $formula = $this->makeFormula(['type' => 'custom', 'expression' => '']);
        $this->engine->evaluate($formula, targetValue: 100, realizationValue: 30);
    }

    public function test_custom_formula_division_by_zero_propagates(): void
    {
        $this->expectException(DivisionByZeroFormulaException::class);

        $formula = $this->makeFormula(['type' => 'custom', 'expression' => 'target / realisasi']);
        $this->engine->evaluate($formula, targetValue: 100, realizationValue: 0);
    }
}