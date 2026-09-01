<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realization_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('realization_id')
                ->constrained('realizations')
                ->restrictOnDelete();
            $table->string('file_path');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('realization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realization_attachments');
    }
};