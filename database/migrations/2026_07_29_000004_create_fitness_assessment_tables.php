<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fitness_assessment_forms')) {
            Schema::create('fitness_assessment_forms', function (Blueprint $table) {
                $table->id();
                $table->string('slug');
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->json('questions');
                $table->boolean('is_active')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['slug', 'version']);
                $table->index(['is_active', 'published_at']);
            });
        }

        if (! Schema::hasTable('fitness_assessments')) {
            Schema::create('fitness_assessments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('form_id')->constrained('fitness_assessment_forms')->restrictOnDelete();
                $table->json('answers')->nullable();
                $table->enum('status', ['draft', 'submitted', 'reviewed'])->default('draft');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('coach_notes')->nullable();
                $table->timestamps();

                $table->unique(['client_id', 'form_id']);
                $table->index(['client_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fitness_assessments');
        Schema::dropIfExists('fitness_assessment_forms');
    }
};
