<?php

use App\Models\Annonce;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('annonces') || Schema::hasColumn('annonces', 'max_participants')) {
            return;
        }

        Schema::table('annonces', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_participants')->nullable()->after('duration');
        });

        DB::table('annonces')
            ->where(function ($query) {
                $query->where('announcement_type', 'coach_service')
                    ->orWhereNull('announcement_type');
            })
            ->update(['max_participants' => Annonce::DEFAULT_MAX_PARTICIPANTS]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('annonces') || ! Schema::hasColumn('annonces', 'max_participants')) {
            return;
        }

        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn('max_participants');
        });
    }
};
