<?php

namespace Tests\Unit\Services\Formula\Calculators;

use App\Services\Formula\Calculators\NilaiLangsungCalculator;
use PHPUnit\Framework\TestCase;

class NilaiLangsungCalculatorTest extends TestCase
{
    public function test_always_returns_null_achievement_and_deviation(): void
    {
        $calculator = new NilaiLangsungCalculator();
        $result = $calculator->calculate(targetValue: 100, realizationValue: 80);

        $this->assertNull($result->achievementPct);
        $this->assertNull($result->deviation);
    }

    public function test_null_result_even_with_zero_values(): void
    {
        // Pass-through -- tidak ada pembagian sama sekali, jadi target/realisasi=0 aman
        $calculator = new NilaiLangsungCalculator();
        $result = $calculator->calculate(targetValue: 0, realizationValue: 0);

        $this->assertNull($result->achievementPct);
        $this->assertNull($result->deviation);
    }
}