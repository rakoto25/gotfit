<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('stripe_transfer_id')->nullable()->after('payment_intent_id');
            $table->timestamp('transferred_at')->nullable()->after('stripe_transfer_id');
            $table->string('payout_status')->default('pending')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_transfer_id',
                'transferred_at',
                'payout_status',
            ]);
        });
    }
};
