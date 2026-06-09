<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'cover_photo')) {
                $table->string('cover_photo')->nullable()->after('photo');
            }
            if (!Schema::hasColumn('users', 'account_status')) {
                $table->enum('account_status', ['pending', 'approved', 'rejected', 'suspended'])->default('approved')->after('address');
            }
            if (!Schema::hasColumn('users', 'validated_by')) {
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete()->after('account_status');
            }
            if (!Schema::hasColumn('users', 'validated_at')) {
                $table->timestamp('validated_at')->nullable()->after('validated_by');
            }
            if (!Schema::hasColumn('users', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('validated_at');
            }
            if (!Schema::hasColumn('users', 'fcm_token')) {
                $table->text('fcm_token')->nullable()->after('rejection_reason');
            }
        });

        Schema::table('annonces', function (Blueprint $table) {
            if (!Schema::hasColumn('annonces', 'category')) $table->string('category')->nullable()->after('contenu');
            if (!Schema::hasColumn('annonces', 'type_prestation')) $table->string('type_prestation')->nullable()->after('category');
            if (!Schema::hasColumn('annonces', 'price')) $table->decimal('price', 10, 2)->default(0)->after('type_prestation');
            if (!Schema::hasColumn('annonces', 'duration')) $table->integer('duration')->nullable()->comment('Durée en minutes')->after('price');
            if (!Schema::hasColumn('annonces', 'is_online')) $table->boolean('is_online')->default(false)->after('duration');
            if (!Schema::hasColumn('annonces', 'location')) $table->string('location')->nullable()->after('is_online');
            if (!Schema::hasColumn('annonces', 'city')) $table->string('city')->nullable()->after('location');
            if (!Schema::hasColumn('annonces', 'address')) $table->string('address')->nullable()->after('city');
            if (!Schema::hasColumn('annonces', 'latitude')) $table->decimal('latitude', 10, 7)->nullable()->after('address');
            if (!Schema::hasColumn('annonces', 'longitude')) $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            if (!Schema::hasColumn('annonces', 'available_days')) $table->json('available_days')->nullable()->after('longitude');
            if (!Schema::hasColumn('annonces', 'available_hours')) $table->json('available_hours')->nullable()->after('available_days');
            if (!Schema::hasColumn('annonces', 'image')) $table->string('image')->nullable()->after('available_hours');
            if (!Schema::hasColumn('annonces', 'is_boosted')) $table->boolean('is_boosted')->default(false)->after('image');
            if (!Schema::hasColumn('annonces', 'boost_until')) $table->timestamp('boost_until')->nullable()->after('is_boosted');
        });

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'user_id')) $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete()->after('id');
            if (!Schema::hasColumn('documents', 'document_type')) $table->string('document_type')->nullable()->after('name');
            if (!Schema::hasColumn('documents', 'validated_by')) $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            if (!Schema::hasColumn('documents', 'validated_at')) $table->timestamp('validated_at')->nullable()->after('validated_by');
            if (!Schema::hasColumn('documents', 'rejection_reason')) $table->text('rejection_reason')->nullable()->after('validated_at');
        });

        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'annonce_id')) $table->foreignId('annonce_id')->nullable()->constrained('annonces')->nullOnDelete()->after('id');
            if (!Schema::hasColumn('reservations', 'price')) $table->decimal('price', 10, 2)->default(0)->after('note');
            if (!Schema::hasColumn('reservations', 'service_fee_rate')) $table->decimal('service_fee_rate', 5, 2)->default(5)->after('price');
            if (!Schema::hasColumn('reservations', 'service_fee_amount')) $table->decimal('service_fee_amount', 10, 2)->default(0)->after('service_fee_rate');
            if (!Schema::hasColumn('reservations', 'commission_rate')) $table->decimal('commission_rate', 5, 2)->default(12)->after('service_fee_amount');
            if (!Schema::hasColumn('reservations', 'commission_amount')) $table->decimal('commission_amount', 10, 2)->default(0)->after('commission_rate');
            if (!Schema::hasColumn('reservations', 'intervenant_amount')) $table->decimal('intervenant_amount', 10, 2)->default(0)->after('commission_amount');
            if (!Schema::hasColumn('reservations', 'total_client_amount')) $table->decimal('total_client_amount', 10, 2)->default(0)->after('intervenant_amount');
            if (!Schema::hasColumn('reservations', 'currency')) $table->string('currency', 3)->default('eur')->after('total_client_amount');
            if (!Schema::hasColumn('reservations', 'payment_intent_id')) $table->string('payment_intent_id')->nullable()->after('payment_status');
            if (!Schema::hasColumn('reservations', 'paid_at')) $table->timestamp('paid_at')->nullable()->after('payment_intent_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'reservation_id')) $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete()->after('id');
            if (!Schema::hasColumn('payments', 'service_fee')) $table->decimal('service_fee', 10, 2)->default(0)->after('amount');
            if (!Schema::hasColumn('payments', 'commission_rate')) $table->decimal('commission_rate', 5, 2)->default(12)->after('service_fee');
            if (!Schema::hasColumn('payments', 'net_amount')) $table->decimal('net_amount', 10, 2)->default(0)->after('intervenant_amount');
            if (!Schema::hasColumn('payments', 'currency')) $table->string('currency', 3)->default('eur')->after('net_amount');
        });

        if (!Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('intervenant_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');
                $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('moderated_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->unique(['reservation_id', 'client_id']);
            });
        }

        if (!Schema::hasTable('missions')) {
            Schema::create('missions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('structure_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('description');
                $table->string('category')->nullable();
                $table->decimal('budget', 10, 2)->default(0);
                $table->date('mission_date')->nullable();
                $table->time('mission_time')->nullable();
                $table->string('location')->nullable();
                $table->string('city')->nullable();
                $table->string('address')->nullable();
                $table->enum('status', ['draft', 'published', 'assigned', 'completed', 'cancelled'])->default('published');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('candidatures')) {
            Schema::create('candidatures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('intervenant_id')->constrained('users')->cascadeOnDelete();
                $table->text('message')->nullable();
                $table->decimal('proposed_price', 10, 2)->nullable();
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->timestamps();
                $table->unique(['mission_id', 'intervenant_id']);
            });
        }

        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('value');
                $table->string('description')->nullable();
                $table->timestamps();
            });

            DB::table('business_settings')->insert([
                ['key' => 'client_service_fee_rate', 'value' => '5', 'description' => 'Frais standard côté client (%)', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'client_loyalty_service_fee_rate', 'value' => '4', 'description' => 'Frais fidélité côté client (%)', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'intervenant_commission_rate', 'value' => '12', 'description' => 'Commission standard intervenant (%)', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'intervenant_first_month_commission_rate', 'value' => '6', 'description' => 'Commission premier mois intervenant (%)', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'intervenant_loyalty_commission_rate', 'value' => '10', 'description' => 'Commission fidélité intervenant (%)', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
        Schema::dropIfExists('candidatures');
        Schema::dropIfExists('missions');
        Schema::dropIfExists('reviews');
    }
};
