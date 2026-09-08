<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon', 40)->default('messages-square');
            $table->string('color', 20)->default('#ea580c');
            $table->boolean('is_official')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('forum_discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('forum_channels')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->boolean('is_pinned')->default(false)->index();
            $table->boolean('is_locked')->default(false)->index();
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pinned_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['channel_id', 'is_pinned', 'last_activity_at'], 'forum_discussions_listing_idx');
            $table->index(['author_id', 'created_at']);
        });

        Schema::create('forum_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_id')->constrained('forum_discussions')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('forum_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['discussion_id', 'parent_id', 'created_at'], 'forum_comments_thread_idx');
        });

        Schema::create('forum_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('discussion_id')->nullable()->constrained('forum_discussions')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('forum_comments')->cascadeOnDelete();
            $table->string('type', 24)->default('like');
            $table->timestamps();

            $table->index(['discussion_id', 'type']);
            $table->index(['comment_id', 'type']);
            $table->unique(['user_id', 'discussion_id'], 'forum_reactions_user_discussion_unique');
            $table->unique(['user_id', 'comment_id'], 'forum_reactions_user_comment_unique');
        });

        Schema::create('forum_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('discussion_id')->nullable()->constrained('forum_discussions')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('forum_comments')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['mentioned_user_id', 'created_at']);
        });

        Schema::create('forum_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('title', 180);
            $table->string('body', 500)->nullable();
            $table->string('url', 255)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at'], 'forum_notifications_inbox_idx');
        });

        Schema::create('forum_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('discussion_id')->nullable()->constrained('forum_discussions')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('forum_comments')->cascadeOnDelete();
            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('moderator_note')->nullable();
            $table->timestamps();

            $table->index(['reporter_id', 'created_at']);
            $table->unique(['reporter_id', 'discussion_id', 'status'], 'forum_reports_discussion_status_unique');
            $table->unique(['reporter_id', 'comment_id', 'status'], 'forum_reports_comment_status_unique');
        });

        $now = now();
        $channels = [
            ['name' => 'Annonces GotFit', 'slug' => 'annonces-gotfit', 'description' => 'Informations officielles de la plateforme.', 'icon' => 'megaphone', 'color' => '#0f172a', 'is_official' => true, 'sort_order' => 0],
            ['name' => 'Général', 'slug' => 'general', 'description' => 'Échanges libres entre coachs vérifiés.', 'icon' => 'messages-square', 'color' => '#ea580c', 'is_official' => false, 'sort_order' => 10],
            ['name' => 'Entraînement & méthodes', 'slug' => 'entrainement-methodes', 'description' => 'Programmation, exercices et retours d’expérience.', 'icon' => 'dumbbell', 'color' => '#16a34a', 'is_official' => false, 'sort_order' => 20],
            ['name' => 'Nutrition & récupération', 'slug' => 'nutrition-recuperation', 'description' => 'Conseils pratiques et veille professionnelle.', 'icon' => 'heart-pulse', 'color' => '#0284c7', 'is_official' => false, 'sort_order' => 30],
            ['name' => 'Développement professionnel', 'slug' => 'developpement-professionnel', 'description' => 'Activité, clientèle et bonnes pratiques métier.', 'icon' => 'briefcase-business', 'color' => '#7c3aed', 'is_official' => false, 'sort_order' => 40],
        ];

        foreach ($channels as $channel) {
            DB::table('forum_channels')->insert($channel + [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('coach_forum_posts')) {
            $generalId = DB::table('forum_channels')->where('slug', 'general')->value('id');

            DB::table('coach_forum_posts')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get()
                ->each(function ($post) use ($generalId) {
                    DB::table('forum_discussions')->insert([
                        'channel_id' => $generalId,
                        'author_id' => $post->user_id,
                        'title' => 'Échange de la communauté',
                        'body' => $post->content,
                        'last_activity_at' => $post->updated_at ?: $post->created_at,
                        'created_at' => $post->created_at,
                        'updated_at' => $post->updated_at,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_reports');
        Schema::dropIfExists('forum_notifications');
        Schema::dropIfExists('forum_mentions');
        Schema::dropIfExists('forum_reactions');
        Schema::dropIfExists('forum_comments');
        Schema::dropIfExists('forum_discussions');
        Schema::dropIfExists('forum_channels');
    }
};
