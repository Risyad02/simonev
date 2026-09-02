<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')
                ->constrained('performance_structure')
                ->restrictOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index('structure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};