<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'receiver_id')) {
                $table->unsignedBigInteger('receiver_id')->nullable()->index();
            }

            if (!Schema::hasColumn('messages', 'subject')) {
                $table->string('subject')->nullable();
            }

            if (!Schema::hasColumn('messages', 'is_read')) {
                $table->boolean('is_read')->default(false)->index();
            }

            if (!Schema::hasColumn('messages', 'read_at')) {
                $table->timestamp('read_at')->nullable();
            }

            if (!Schema::hasColumn('messages', 'replied_at')) {
                $table->timestamp('replied_at')->nullable();
            }

            if (!Schema::hasColumn('messages', 'status')) {
                $table->string('status', 50)->default('sent')->index();
            }

            if (!Schema::hasColumn('messages', 'is_admin_broadcast')) {
                $table->boolean('is_admin_broadcast')->default(false)->index();
            }

            if (!Schema::hasColumn('messages', 'broadcast_group')) {
                $table->string('broadcast_group', 80)->nullable()->index();
            }

            if (!Schema::hasColumn('messages', 'broadcast_target_role')) {
                $table->string('broadcast_target_role', 80)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $columns = [
                'broadcast_target_role',
                'broadcast_group',
                'is_admin_broadcast',
                'status',
                'replied_at',
                'read_at',
                'is_read',
                'subject',
                'receiver_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
