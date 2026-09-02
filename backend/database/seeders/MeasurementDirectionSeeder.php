<?php

namespace Database\Seeders;

use App\Models\MeasurementDirection;
use Illuminate\Database\Seeder;

class MeasurementDirectionSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Naik Lebih Baik', 'description' => 'Capaian dinilai lebih baik apabila realisasi lebih tinggi dari target'],
            ['name' => 'Turun Lebih Baik', 'description' => 'Capaian dinilai lebih baik apabila realisasi lebih rendah dari target'],
            ['name' => 'Netral', 'description' => 'Arah pengukuran tidak dievaluasi sebagai lebih baik/buruk'],
        ];

        foreach ($items as $item) {
            MeasurementDirection::updateOrCreate(['name' => $item['name']], $item);
        }
    }
}