<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_category_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')
                ->constrained('indicators')
                ->restrictOnDelete();
            $table->foreignId('category_id')
                ->constrained('indicator_categories')
                ->restrictOnDelete();
            $table->foreignId('planning_document_id')
                ->nullable()
                ->constrained('planning_documents')
                ->nullOnDelete();
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();

            $table->index(['indicator_id', 'is_active']);
            $table->index('category_id');
            $table->index('planning_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_category_assignments');
    }
};