<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TYPE reservations_status_t ADD VALUE IF NOT EXISTS 'annule'");

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE reservations MODIFY status ENUM('attente', 'confirme', 'refuse', 'annule', 'realise') NOT NULL DEFAULT 'attente'"
            );
        }
    }

    public function down(): void
    {
        // PostgreSQL ne permet pas de retirer proprement une valeur d'enum
        // sans recréer le type. La conserver évite une migration destructive.
    }
};
