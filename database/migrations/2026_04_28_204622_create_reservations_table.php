<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // CLIENT (celui qui réserve)
            $table->unsignedBigInteger('client_id');

            // INTERVENANT (prestataire / agent / staff)
            $table->unsignedBigInteger('intervenant_id');

            // Infos réservation
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->integer('guests')->default(1);
            $table->text('note')->nullable();

            // STATUS
            $table->enum('status', [
                'attente',
                'confirme',
                'refuse',
                'realise'
            ])->default('attente');

            $table->timestamps();

            // Foreign keys (si tu as les tables users ou intervenants)
            $table->foreign('client_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('intervenant_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
