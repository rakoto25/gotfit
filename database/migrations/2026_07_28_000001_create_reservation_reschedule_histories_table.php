<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_reschedule_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->date('old_reservation_date');
            $table->time('old_reservation_time');
            $table->date('new_reservation_date');
            $table->time('new_reservation_time');
            $table->text('note')->nullable();
            $table->string('source', 50)->default('gotfit-mobile');
            $table->timestamp('coach_notified_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_reschedule_histories');
    }
};
