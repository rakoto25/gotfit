<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('annonces')) {
            return;
        }

        $updates = [];

        if (Schema::hasColumn('annonces', 'type_prestation')) {
            $updates['type_prestation'] = 'visio';
        }
        if (Schema::hasColumn('annonces', 'is_online')) {
            $updates['is_online'] = true;
        }
        if (Schema::hasColumn('annonces', 'location')) {
            $updates['location'] = 'Visio GotFit';
        }
        foreach (['city', 'address', 'latitude', 'longitude'] as $column) {
            if (Schema::hasColumn('annonces', $column)) {
                $updates[$column] = null;
            }
        }

        if ($updates !== []) {
            DB::table('annonces')->update($updates);
        }

        // Les visios autonomes n’ont pas de parcours Stripe dédié : on les rend
        // gratuites pour éviter des inscriptions bloquées en statut 'unpaid'.
        if (Schema::hasTable('visio_sessions')) {
            if (Schema::hasColumn('visio_sessions', 'provider')) {
                DB::table('visio_sessions')->update(['provider' => 'livekit']);
            }

            if (Schema::hasColumn('visio_sessions', 'reservation_id')
                && Schema::hasColumn('visio_sessions', 'price')) {
                DB::table('visio_sessions')
                    ->whereNull('reservation_id')
                    ->update(['price' => 0]);
            }
        }
    }

    public function down(): void
    {
        // La conversion des anciennes annonces physiques est volontairement irréversible.
    }
};
