<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visio_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visio_session_id')->constrained('visio_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['coach', 'participant'])->default('participant');
            $table->enum('status', [
                'invited',
                'reserved',
                'paid',
                'joined',
                'left',
                'cancelled',
                'no_show',
            ])->default('reserved');
            $table->enum('payment_status', [
                'unpaid',
                'pending',
                'paid',
                'refunded',
            ])->default('unpaid');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('payment_intent_id')->nullable()->index();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('joined_at')->nullable();
            $table->dateTime('left_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['visio_session_id', 'user_id']);
            $table->index(['visio_session_id', 'role', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visio_participants');
    }
};
