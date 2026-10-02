<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('session_count');
            $table->unsignedBigInteger('amount_total');
            $table->string('currency', 3)->default('eur');
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->unique()->constrained('offers')->nullOnDelete();
        });

        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->unique()->constrained('offers')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->string('stripe_payment_intent_id')->unique();
            $table->string('stripe_charge_id')->nullable()->index();
            $table->unsignedBigInteger('amount_total');
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('coach_net_amount');
            $table->unsignedBigInteger('amount_transferred')->default(0);
            $table->unsignedSmallInteger('session_count');
            $table->unsignedSmallInteger('completed_sessions')->default(0);
            $table->string('currency', 3)->default('eur');
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('pack_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('packs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->unsignedBigInteger('amount_due');
            $table->string('status', 40)->default('pending')->index();
            $table->string('payout_status', 30)->default('pending')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('validation_deadline')->nullable()->index();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disputed_at')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->string('stripe_transfer_id')->nullable()->unique();
            $table->timestamp('transferred_at')->nullable();
            $table->text('payout_error')->nullable();
            $table->timestamps();
            $table->unique(['pack_id', 'sequence']);
        });

        Schema::create('pack_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_session_id')->unique()->constrained('pack_sessions')->cascadeOnDelete();
            $table->foreignId('pack_id')->constrained('packs')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->string('stripe_transfer_id')->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('eur');
            $table->string('status', 30)->default('processing')->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->string('currency', 3)->default('eur');
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained('packs')->nullOnDelete();
            $table->string('payment_intent_id')->nullable()->index();
            $table->string('type', 30)->index();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('stripe_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('type')->index();
            $table->string('status', 20)->default('processing')->index();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained('packs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pack_id');
            $table->dropConstrainedForeignId('offer_id');
        });
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('pack_payouts');
        Schema::dropIfExists('pack_sessions');
        Schema::dropIfExists('packs');
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
        });
        Schema::dropIfExists('offers');
    }
};
