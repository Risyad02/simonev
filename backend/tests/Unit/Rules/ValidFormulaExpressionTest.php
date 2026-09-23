<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidFormulaExpression;
use PHPUnit\Framework\TestCase;

class ValidFormulaExpressionTest extends TestCase
{
    private ValidFormulaExpression $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new ValidFormulaExpression();
    }

    public function test_passes_for_valid_expression(): void
    {
        $failed = false;
        $this->rule->validate('expression', '(realisasi / target) * 100', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_fails_for_invalid_syntax(): void
    {
        $failed = false;
        $this->rule->validate('expression', 'target +', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
    }

    public function test_fails_for_disallowed_function_call(): void
    {
        $failed = false;
        $this->rule->validate('expression', 'abs(target)', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
    }

    public function test_null_value_short_circuits_without_failing(): void
    {
        $failed = false;
        $this->rule->validate('expression', null, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_empty_string_short_circuits_without_failing(): void
    {
        $failed = false;
        $this->rule->validate('expression', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}