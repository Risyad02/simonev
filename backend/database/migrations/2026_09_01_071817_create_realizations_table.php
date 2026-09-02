<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')
                ->constrained('targets')
                ->restrictOnDelete();
            $table->decimal('realization_value', 20, 4);
            $table->decimal('achievement_pct', 10, 4)->nullable();
            $table->decimal('deviation', 20, 4)->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('input_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('input_at')->nullable();
            $table->timestamps();

            $table->index('target_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realizations');
    }
};