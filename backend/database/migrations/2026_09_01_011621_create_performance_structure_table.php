<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_structure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('performance_structure')
                ->nullOnDelete();
            $table->foreignId('planning_document_id')
                ->nullable()
                ->constrained('planning_documents')
                ->nullOnDelete();
            $table->string('level_type');
            $table->string('name');
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('parent_id');
            $table->index('planning_document_id');
            $table->index('level_type');
            $table->index('year');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_structure');
    }
};