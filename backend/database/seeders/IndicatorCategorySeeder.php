<?php

namespace Database\Seeders;

use App\Models\IndicatorCategory;
use Illuminate\Database\Seeder;

class IndicatorCategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'IKU', 'description' => 'Indikator Kinerja Utama'],
            ['name' => 'IKD/IKK', 'description' => 'Indikator Kinerja Daerah / Indikator Kinerja Kunci'],
        ];

        foreach ($items as $item) {
            IndicatorCategory::updateOrCreate(['name' => $item['name']], $item);
        }
    }
}