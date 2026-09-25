<?php

namespace Database\Seeders;

use App\Models\Formula;
use Illuminate\Database\Seeder;

class FormulaSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Persentase Capaian', 'formula_type' => 'persentase_capaian', 'type' => 'system', 'expression' => null, 'description' => 'Realisasi dibagi Target dikali 100'],
            ['name' => 'Target per Realisasi', 'formula_type' => 'target_per_realisasi', 'type' => 'system', 'expression' => null, 'description' => 'Target dibagi Realisasi dikali 100'],
            ['name' => 'Akumulasi', 'formula_type' => 'akumulasi', 'type' => 'system', 'expression' => null, 'description' => 'Penjumlahan realisasi bertahap terhadap target'],
            ['name' => 'Rata-rata', 'formula_type' => 'rata_rata', 'type' => 'system', 'expression' => null, 'description' => 'Rata-rata realisasi periode terhadap target'],
            ['name' => 'Nilai Langsung', 'formula_type' => 'nilai_langsung', 'type' => 'system', 'expression' => null, 'description' => 'Realisasi dicatat langsung tanpa perhitungan formula'],
            ['name' => 'Bobot', 'formula_type' => 'bobot', 'type' => 'system', 'expression' => null, 'description' => 'Capaian dihitung berdasarkan bobot indikator'],
        ];

        foreach ($items as $item) {
            Formula::updateOrCreate(['name' => $item['name']], $item);
        }
    }
}