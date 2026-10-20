<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_favorites')) {
            return;
        }

        Schema::create('coach_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'coach_id']);
            $table->index(['coach_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_favorites');
    }
};
