<?php

namespace Database\Seeders;

use App\Models\ReportingPeriod;
use Illuminate\Database\Seeder;

class ReportingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Bulanan', 'periods_per_year' => 12],
            ['name' => 'Triwulanan', 'periods_per_year' => 4],
            ['name' => 'Semesteran', 'periods_per_year' => 2],
            ['name' => 'Tahunan', 'periods_per_year' => 1],
        ];

        foreach ($items as $item) {
            ReportingPeriod::updateOrCreate(['name' => $item['name']], $item);
        }
    }
}