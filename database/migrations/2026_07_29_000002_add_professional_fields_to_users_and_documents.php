<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'siret')) {
                $table->string('siret', 14)->nullable()->unique()->after('address');
            }

            if (! Schema::hasColumn('users', 'siret_verified_at')) {
                $table->timestamp('siret_verified_at')->nullable()->after('siret');
            }

            if (! Schema::hasColumn('users', 'siret_verified_by')) {
                $table->foreignId('siret_verified_by')
                    ->nullable()
                    ->after('siret_verified_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'document_number')) {
                $table->string('document_number')->nullable()->after('document_type');
            }

            if (! Schema::hasColumn('documents', 'issuing_organization')) {
                $table->string('issuing_organization')->nullable()->after('document_number');
            }

            if (! Schema::hasColumn('documents', 'issued_at')) {
                $table->date('issued_at')->nullable()->after('issuing_organization');
            }

            if (! Schema::hasColumn('documents', 'expires_at')) {
                $table->date('expires_at')->nullable()->after('issued_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('documents', 'document_number') ? 'document_number' : null,
                Schema::hasColumn('documents', 'issuing_organization') ? 'issuing_organization' : null,
                Schema::hasColumn('documents', 'issued_at') ? 'issued_at' : null,
                Schema::hasColumn('documents', 'expires_at') ? 'expires_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'siret_verified_by')) {
                $table->dropConstrainedForeignId('siret_verified_by');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('users', 'siret_verified_at') ? 'siret_verified_at' : null,
                Schema::hasColumn('users', 'siret') ? 'siret' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
