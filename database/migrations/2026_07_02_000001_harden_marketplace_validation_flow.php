<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'stripe_charge_id')) {
                $table->string('stripe_charge_id')->nullable()->after('payment_intent_id');
            }

            if (!Schema::hasColumn('reservations', 'validated_by')) {
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete()->after('validated_at');
            }

            if (!Schema::hasColumn('reservations', 'validation_deadline')) {
                $table->timestamp('validation_deadline')->nullable()->after('validated_by');
            }

            if (!Schema::hasColumn('reservations', 'disputed_at')) {
                $table->timestamp('disputed_at')->nullable()->after('validation_deadline');
            }

            if (!Schema::hasColumn('reservations', 'dispute_reason')) {
                $table->text('dispute_reason')->nullable()->after('disputed_at');
            }

            if (!Schema::hasColumn('reservations', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('dispute_reason');
            }

            if (!Schema::hasColumn('reservations', 'resolution_note')) {
                $table->text('resolution_note')->nullable()->after('resolved_at');
            }

            if (!Schema::hasColumn('reservations', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('resolution_note');
            }

            if (!Schema::hasColumn('reservations', 'refund_reason')) {
                $table->text('refund_reason')->nullable()->after('refunded_at');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'stripe_charge_id')) {
                $table->string('stripe_charge_id')->nullable()->after('payment_intent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'stripe_charge_id')) {
                $table->dropColumn('stripe_charge_id');
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'validated_by')) {
                $table->dropForeign(['validated_by']);
            }

            $columns = [
                'stripe_charge_id',
                'validated_by',
                'validation_deadline',
                'disputed_at',
                'dispute_reason',
                'resolved_at',
                'resolution_note',
                'refunded_at',
                'refund_reason',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
