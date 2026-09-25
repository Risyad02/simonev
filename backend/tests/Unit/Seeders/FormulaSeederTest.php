<?php

namespace Tests\Unit\Seeders;

use App\Models\Formula;
use Database\Seeders\FormulaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormulaSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test untuk bug yang ditemukan 2026-09-25: migrate:fresh
     * --seed dari kondisi benar-benar kosong menghasilkan seluruh 6
     * formula bawaan ber-type 'custom' (default kolom), bukan 'system',
     * karena FormulaSeeder tidak menyertakan 'type' secara eksplisit dan
     * migration data-fix Checkpoint 1 berjalan SEBELUM seeder mengisi
     * data (update 0 baris, tidak error, tapi juga tidak berefek).
     *
     * Test ini memverifikasi FormulaSeeder benar dengan sendirinya,
     * tidak bergantung pada urutan/keberadaan migration data-fix lain.
     */
    public function test_seeded_formulas_have_system_type(): void
    {
        (new FormulaSeeder())->run();

        $systemFormulaTypes = [
            'persentase_capaian',
            'target_per_realisasi',
            'akumulasi',
            'rata_rata',
            'nilai_langsung',
            'bobot',
        ];

        foreach ($systemFormulaTypes as $formulaType) {
            $this->assertDatabaseHas('formulas', [
                'formula_type' => $formulaType,
                'type' => 'system',
            ]);
        }

        $this->assertSame(6, Formula::where('type', 'system')->count());
    }

    public function test_re_running_seeder_fixes_existing_wrong_type_data(): void
    {
        // Simulasikan kondisi bug: baris ada tapi type salah (custom)
        Formula::create([
            'name' => 'Persentase Capaian',
            'formula_type' => 'persentase_capaian',
            'type' => 'custom', // salah, hasil bug lama
            'expression' => null,
        ]);

        (new FormulaSeeder())->run();

        $this->assertDatabaseHas('formulas', [
            'name' => 'Persentase Capaian',
            'type' => 'system',
        ]);

        // Pastikan tidak duplikat baris (updateOrCreate berdasarkan name)
        $this->assertSame(1, Formula::where('name', 'Persentase Capaian')->count());
    }
}