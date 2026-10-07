<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('audience', 30);
            $table->string('title');
            $table->longText('content');
            $table->string('version', 50)->default('1.0');
            $table->timestamp('effective_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('legal_documents')->insert([
            [
                'slug' => 'client-achat',
                'audience' => 'client',
                'title' => 'Conditions de vente applicables aux prestations',
                'content' => file_get_contents(resource_path('legal/cgv-client-achat.md')),
                'version' => '1.0',
                'effective_at' => $now,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'intervenants',
                'audience' => 'intervenant',
                'title' => 'Conditions générales de vente et d’utilisation',
                'content' => file_get_contents(resource_path('legal/cgv-intervenants.md')),
                'version' => '1.0',
                'effective_at' => $now,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
