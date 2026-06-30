<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('prestation_status')->default('pending')->after('payment_status');
            $table->string('payout_status')->default('pending')->after('prestation_status');
            $table->string('stripe_transfer_id')->nullable()->after('payment_intent_id');
            $table->timestamp('validated_at')->nullable()->after('paid_at');
            $table->timestamp('transferred_at')->nullable()->after('validated_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'prestation_status',
                'payout_status',
                'stripe_transfer_id',
                'validated_at',
                'transferred_at',
            ]);
        });
    }
};
