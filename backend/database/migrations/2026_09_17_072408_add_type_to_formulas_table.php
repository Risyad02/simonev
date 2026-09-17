<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * formula_type yang tergolong "system" per FormulaSeeder.php (verified,
     * bukan diasumsikan dari jumlah row). Formula lain (mis. buatan user
     * saat testing/manual, seperti 'simple' pada FormulaTest.php) tetap
     * default 'custom'.
     */
    private const SYSTEM_FORMULA_TYPES = [
        'persentase_capaian',
        'target_per_realisasi',
        'akumulasi',
        'rata_rata',
        'nilai_langsung',
        'bobot',
    ];

    public function up(): void
    {
        Schema::table('formulas', function (Blueprint $table) {
            $table->string('type')->default('custom')->after('formula_type');
            $table->index('type');
        });

        DB::table('formulas')
            ->whereIn('formula_type', self::SYSTEM_FORMULA_TYPES)
            ->update(['type' => 'system']);
    }

    public function down(): void
    {
        Schema::table('formulas', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};