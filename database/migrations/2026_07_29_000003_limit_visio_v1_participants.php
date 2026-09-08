<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visio_sessions')) {
            return;
        }

        // Historique V1 : limite initiale à deux coachés. Une migration
        // ultérieure étend la salle à cinq personnes au total.
        DB::table('visio_sessions')
            ->where(function ($query) {
                $query->whereNull('max_participants')
                    ->orWhere('max_participants', '>', 2);
            })
            ->update(['max_participants' => 2]);

        DB::table('visio_sessions')
            ->where('min_participants', '>', 2)
            ->update(['min_participants' => 2]);
    }

    public function down(): void
    {
        // La valeur historique ne peut pas être reconstruite de façon fiable.
    }
};
