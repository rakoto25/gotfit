<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'presentation_video')) {
                $table->string('presentation_video')->nullable()->after('cover_photo');
            }

            if (!Schema::hasColumn('users', 'presentation_video_duration_seconds')) {
                $table->unsignedSmallInteger('presentation_video_duration_seconds')->nullable()->after('presentation_video');
            }

            if (!Schema::hasColumn('users', 'coach_title')) {
                $table->string('coach_title')->nullable()->after('bio');
            }

            if (!Schema::hasColumn('users', 'coach_short_description')) {
                $table->string('coach_short_description', 500)->nullable()->after('coach_title');
            }

            if (!Schema::hasColumn('users', 'coach_speciality')) {
                $table->string('coach_speciality')->nullable()->after('coach_short_description');
            }

            if (!Schema::hasColumn('users', 'coach_experience_years')) {
                $table->unsignedTinyInteger('coach_experience_years')->nullable()->after('coach_speciality');
            }

            if (!Schema::hasColumn('users', 'coach_certifications')) {
                $table->text('coach_certifications')->nullable()->after('coach_experience_years');
            }

            if (!Schema::hasColumn('users', 'coach_languages')) {
                $table->string('coach_languages', 500)->nullable()->after('coach_certifications');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'presentation_video',
                'presentation_video_duration_seconds',
                'coach_title',
                'coach_short_description',
                'coach_speciality',
                'coach_experience_years',
                'coach_certifications',
                'coach_languages',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
