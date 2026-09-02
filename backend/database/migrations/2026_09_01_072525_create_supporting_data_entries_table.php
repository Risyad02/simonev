<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supporting_data_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('supporting_data_categories')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->json('payload')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('category_id');
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supporting_data_entries');
    }
};