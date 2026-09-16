<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'display_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('display_name', 80)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'display_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('display_name');
            });
        }
    }
};
