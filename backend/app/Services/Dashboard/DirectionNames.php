<?php

namespace App\Services\Dashboard;

/**
 * Nama arah pengukuran dari MeasurementDirectionSeeder yang menentukan apakah
 * capaian layak dirata-rata. Tabel arah belum punya kode, jadi pencocokan
 * memakai nama; satu sumber ini mencegah dua service menyimpang diam-diam.
 */
final class DirectionNames
{
    public const UP = 'Naik Lebih Baik';
    public const DOWN = 'Turun Lebih Baik';
}