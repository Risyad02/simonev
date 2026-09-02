<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')
                ->constrained('indicators')
                ->restrictOnDelete();
            $table->foreignId('planning_document_id')
                ->nullable()
                ->constrained('planning_documents')
                ->nullOnDelete();
            $table->foreignId('unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();
            $table->foreignId('formula_id')
                ->constrained('formulas')
                ->restrictOnDelete();
            $table->foreignId('reporting_period_id')
                ->constrained('reporting_periods')
                ->restrictOnDelete();
            $table->foreignId('direction_id')
                ->nullable()
                ->constrained('measurement_directions')
                ->nullOnDelete();
            $table->text('operational_definition')->nullable();
            $table->text('measurement_method')->nullable();
            $table->string('data_source')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('indicator_id');
            $table->index('planning_document_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_versions');
    }
};