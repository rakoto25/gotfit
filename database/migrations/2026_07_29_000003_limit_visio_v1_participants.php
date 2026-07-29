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

        // V1 : deux coachés au maximum. Le coach est compté séparément,
        // soit trois personnes au total dans la salle.
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
