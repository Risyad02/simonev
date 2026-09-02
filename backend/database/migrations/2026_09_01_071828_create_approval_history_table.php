<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('realization_id')
                ->constrained('realizations')
                ->restrictOnDelete();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index('realization_id');
            $table->index('to_status');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_history');
    }
};