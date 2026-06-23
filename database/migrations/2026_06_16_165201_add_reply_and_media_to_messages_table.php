<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('sender_id');
            }

            if (!Schema::hasColumn('messages', 'type')) {
                $table->string('type')->default('text')->after('message');
            }

            if (!Schema::hasColumn('messages', 'media_url')) {
                $table->string('media_url')->nullable()->after('type');
            }

            if (!Schema::hasColumn('messages', 'media_type')) {
                $table->string('media_type')->nullable()->after('media_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'parent_id')) {
                $table->dropColumn('parent_id');
            }

            if (Schema::hasColumn('messages', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('messages', 'media_url')) {
                $table->dropColumn('media_url');
            }

            if (Schema::hasColumn('messages', 'media_type')) {
                $table->dropColumn('media_type');
            }
        });
    }
};
