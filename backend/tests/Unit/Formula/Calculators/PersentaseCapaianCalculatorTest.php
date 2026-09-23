<?php

namespace Tests\Unit\Services\Formula\Calculators;

use App\Services\Formula\Calculators\PersentaseCapaianCalculator;
use App\Services\Formula\Exceptions\DivisionByZeroFormulaException;
use PHPUnit\Framework\TestCase;

class PersentaseCapaianCalculatorTest extends TestCase
{
    private PersentaseCapaianCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PersentaseCapaianCalculator();
    }

    public function test_calculates_achievement_and_deviation(): void
    {
        $result = $this->calculator->calculate(targetValue: 100, realizationValue: 80);

        $this->assertSame(80.0, $result->achievementPct);
        $this->assertSame(-20.0, $result->deviation);
    }

    public function test_achievement_above_100_gives_positive_deviation(): void
    {
        $result = $this->calculator->calculate(targetValue: 100, realizationValue: 120);

        $this->assertSame(120.0, $result->achievementPct);
        $this->assertSame(20.0, $result->deviation);
    }

    public function test_target_zero_throws_division_by_zero(): void
    {
        $this->expectException(DivisionByZeroFormulaException::class);
        $this->calculator->calculate(targetValue: 0, realizationValue: 50);
    }

    public function test_decimal_precision(): void
    {
        $result = $this->calculator->calculate(targetValue: 3, realizationValue: 1);

        $this->assertEqualsWithDelta(33.3333, $result->achievementPct, 0.0001);
    }
}