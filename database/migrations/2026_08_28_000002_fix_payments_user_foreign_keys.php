<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments') || DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('payments_intervenant_id_foreign');
            $table->dropForeign('payments_client_id_foreign');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('intervenant_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('client_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Le retour aux anciennes clés étrangères rendrait les paiements invalides.
    }
};
