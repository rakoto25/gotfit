<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_amount_applied')->default(0)->after('amount_total');
            $table->unsignedBigInteger('stripe_amount_due')->nullable()->after('wallet_amount_applied');
            $table->text('stripe_checkout_url')->nullable()->after('stripe_checkout_session_id');
        });

        Schema::table('packs', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_amount_used')->default(0)->after('amount_total');
            $table->unsignedBigInteger('stripe_amount_paid')->default(0)->after('wallet_amount_used');
            $table->unsignedBigInteger('refunded_amount')->default(0)->after('stripe_amount_paid');
        });

        Schema::table('pack_sessions', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->index()->after('payout_status');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->after('pack_id')->constrained('offers')->nullOnDelete();
        });

        Schema::create('pack_session_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_session_id')->constrained('pack_sessions')->cascadeOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20);
            $table->string('kind', 20)->default('cancellation');
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->boolean('consumes_session')->default(false);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        $now = now();
        $settings = [
            ['key' => 'pack_late_cancellation_hours', 'value' => '24', 'description' => 'Délai avant séance définissant une annulation client tardive (heures)'],
            ['key' => 'pack_late_cancellation_consumes_session', 'value' => '1', 'description' => 'Une annulation client tardive consomme la séance'],
            ['key' => 'pack_no_show_grace_minutes', 'value' => '15', 'description' => 'Délai avant déclaration no-show (minutes)'],
            ['key' => 'pack_no_show_consumes_session', 'value' => '1', 'description' => 'Un no-show client consomme la séance'],
        ];
        foreach ($settings as $setting) {
            DB::table('business_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting + ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('business_settings')->whereIn('key', [
            'pack_late_cancellation_hours',
            'pack_late_cancellation_consumes_session',
            'pack_no_show_grace_minutes',
            'pack_no_show_consumes_session',
        ])->delete();

        Schema::dropIfExists('pack_session_cancellations');
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
        });
        Schema::table('pack_sessions', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
        Schema::table('packs', function (Blueprint $table) {
            $table->dropColumn(['wallet_amount_used', 'stripe_amount_paid', 'refunded_amount']);
        });
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['wallet_amount_applied', 'stripe_amount_due', 'stripe_checkout_url']);
        });
    }
};
