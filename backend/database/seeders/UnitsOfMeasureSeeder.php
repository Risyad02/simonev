<?php

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UnitsOfMeasureSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Persen', 'symbol' => '%'],
            ['name' => 'Rupiah', 'symbol' => 'Rp'],
            ['name' => 'Unit', 'symbol' => null],
            ['name' => 'Orang', 'symbol' => null],
            ['name' => 'Dokumen', 'symbol' => null],
            ['name' => 'Kilometer', 'symbol' => 'Km'],
            ['name' => 'Nilai', 'symbol' => null],
            ['name' => 'Jumlah', 'symbol' => null],
        ];

        foreach ($items as $item) {
            UnitOfMeasure::updateOrCreate(['name' => $item['name']], $item);
        }
    }
}