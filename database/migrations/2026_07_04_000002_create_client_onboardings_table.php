<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_onboardings')) {
            return;
        }

        Schema::create('client_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('goals')->nullable();
            $table->string('level')->nullable();
            $table->json('training_preferences')->nullable();
            $table->json('availability')->nullable();
            $table->json('health_constraints')->nullable();
            $table->json('measurements')->nullable();
            $table->json('lifestyle')->nullable();
            $table->json('emergency_contact')->nullable();
            $table->json('answers')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('is_completed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_onboardings');
    }
};
