<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_document_id')
                ->nullable()
                ->constrained('planning_documents')
                ->nullOnDelete();
            $table->string('document_type');
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedSmallInteger('period_start_year')->nullable();
            $table->unsignedSmallInteger('period_end_year')->nullable();
            $table->unsignedInteger('version_no')->default(1);
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('parent_document_id');
            $table->index('document_type');
            $table->index('year');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_documents');
    }
};