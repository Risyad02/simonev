<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('unit_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('parent_unit_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};