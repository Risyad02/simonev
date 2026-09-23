<?php

namespace Tests\Unit\Services\Formula\Calculators;

use App\Services\Formula\Calculators\TargetPerRealisasiCalculator;
use App\Services\Formula\Exceptions\DivisionByZeroFormulaException;
use PHPUnit\Framework\TestCase;

class TargetPerRealisasiCalculatorTest extends TestCase
{
    private TargetPerRealisasiCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TargetPerRealisasiCalculator();
    }

    public function test_calculates_achievement_and_deviation(): void
    {
        // Indikator "makin kecil makin baik": realisasi lebih rendah dari target = capaian > 100
        $result = $this->calculator->calculate(targetValue: 100, realizationValue: 80);

        $this->assertSame(125.0, $result->achievementPct);
        $this->assertSame(25.0, $result->deviation);
    }

    public function test_realization_zero_throws_division_by_zero(): void
    {
        $this->expectException(DivisionByZeroFormulaException::class);
        $this->calculator->calculate(targetValue: 100, realizationValue: 0);
    }

    public function test_target_zero_is_not_division_by_zero_for_this_formula(): void
    {
        // Formula ini membagi dengan realisasi, bukan target -- target=0 harus aman
        $result = $this->calculator->calculate(targetValue: 0, realizationValue: 50);

        $this->assertSame(0.0, $result->achievementPct);
    }
}