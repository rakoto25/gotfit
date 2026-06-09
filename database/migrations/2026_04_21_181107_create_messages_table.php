<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ancienne table de messages liée directement aux annonces.
        // Renommée pour éviter le conflit avec la vraie table messages des conversations.
        if (!Schema::hasTable('annonce_messages')) {
            Schema::create('annonce_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('annonce_id')->constrained()->onDelete('cascade');
                $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
                $table->text('message');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('annonce_messages');
    }
};
