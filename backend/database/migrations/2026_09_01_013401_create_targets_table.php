<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_version_id')
                ->constrained('indicator_versions')
                ->restrictOnDelete();
            $table->foreignId('planning_document_id')
                ->constrained('planning_documents')
                ->restrictOnDelete();
            $table->string('period_label');
            $table->decimal('target_value', 20, 4);
            $table->unsignedInteger('revision_no')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('indicator_version_id');
            $table->index('planning_document_id');
            $table->index('is_active');
            $table->index('period_label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};