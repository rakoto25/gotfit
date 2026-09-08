<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visio_sessions', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('join_url');
            $table->foreignId('link_created_by')->nullable()->after('share_token')->constrained('users')->nullOnDelete();
            $table->timestamp('link_created_at')->nullable()->after('link_created_by');
        });

        DB::table('visio_sessions')
            ->whereNull('reservation_id')
            ->where('max_participants', 2)
            ->update(['max_participants' => 4]);
    }

    public function down(): void
    {
        Schema::table('visio_sessions', function (Blueprint $table) {
            $table->dropForeign(['link_created_by']);
            $table->dropUnique(['share_token']);
            $table->dropColumn(['share_token', 'link_created_by', 'link_created_at']);
        });
    }
};
