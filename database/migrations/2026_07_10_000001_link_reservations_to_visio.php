<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visio_sessions', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->after('id')->unique()->constrained('reservations')->nullOnDelete();
            $table->foreignId('annonce_id')->nullable()->after('reservation_id')->constrained('annonces')->nullOnDelete();
            $table->string('session_type', 20)->default('group')->after('coach_id')->index();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('visio_session_id')->nullable()->after('intervenant_id')->unique()->constrained('visio_sessions')->nullOnDelete();
        });

        Schema::table('visio_participants', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->after('visio_session_id')->constrained('reservations')->nullOnDelete();
            $table->index(['reservation_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('visio_participants', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropIndex(['reservation_id', 'payment_status']);
            $table->dropColumn('reservation_id');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['visio_session_id']);
            $table->dropUnique(['visio_session_id']);
            $table->dropColumn('visio_session_id');
        });

        Schema::table('visio_sessions', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropForeign(['annonce_id']);
            $table->dropUnique(['reservation_id']);
            $table->dropColumn(['reservation_id', 'annonce_id', 'session_type']);
        });
    }
};
