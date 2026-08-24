<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('annonces', 'announcement_type')) {
            return;
        }

        Schema::table('annonces', function (Blueprint $table) {
            $table->string('announcement_type', 30)
                ->default('coach_service')
                ->after('status')
                ->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('annonces', 'announcement_type')) {
            return;
        }

        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn('announcement_type');
        });
    }
};
