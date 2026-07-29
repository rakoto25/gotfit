<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->filled('siret')) {
            $request->merge([
                'siret' => preg_replace('/\D+/', '', (string) $request->input('siret')),
            ]);
        }

        $user->load('roles');

        return response()->json([
            'status' => 200,
            'message' => 'Profil récupéré avec succès',
            'user' => $this->formatUserProfile($user),
        ]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        Log::info('PROFILE UPDATE FILES', [
            'has_photo' => $request->hasFile('photo'),
            'has_cover_photo' => $request->hasFile('cover_photo'),
            'has_presentation_video' => $request->hasFile('presentation_video'),
            'all_files' => array_keys($request->allFiles()),
        ]);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],

            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'password' => ['sometimes', 'nullable', 'string', 'min:6'],

            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'coach_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'coach_short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'coach_speciality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'coach_experience_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'coach_certifications' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'coach_languages' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'siret' => [
                'sometimes',
                'required',
                Rule::prohibitedIf(fn () => ! $user->hasRole('intervenant')),
                'digits:14',
                Rule::unique('users', 'siret')->ignore($user->id),
            ],

            'photo' => ['sometimes', 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:8192'],
            'cover_photo' => ['sometimes', 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:12288'],
            'presentation_video' => ['sometimes', 'nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-matroska', 'mimes:mp4,mov,webm,mkv', 'max:51200'],
            'remove_photo' => ['sometimes', 'nullable', 'boolean'],
            'remove_cover_photo' => ['sometimes', 'nullable', 'boolean'],
            'remove_presentation_video' => ['sometimes', 'nullable', 'boolean'],
            'fcm_token' => ['sometimes', 'nullable', 'string'],
        ]);

        $presentationVideoDuration = null;

        if ($request->hasFile('presentation_video')) {
            $presentationVideoDuration = $this->getVideoDurationInSeconds($request->file('presentation_video')->getRealPath());

            if ($presentationVideoDuration === null) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Impossible de vérifier la durée de la vidéo. Installez ffprobe/ffmpeg sur le serveur pour limiter la vidéo à 60 secondes.',
                ], 422);
            }

            if ($presentationVideoDuration > 60) {
                return response()->json([
                    'status' => 422,
                    'message' => 'La vidéo de présentation ne doit pas dépasser 60 secondes.',
                    'errors' => [
                        'presentation_video' => ['La vidéo de présentation ne doit pas dépasser 60 secondes.'],
                    ],
                ], 422);
            }
        }

        $data = [];
        $filesToDelete = [];

        foreach (['name', 'email', 'bio', 'phone', 'address'] as $column) {
            if ($request->exists($column)) {
                $data[$column] = $validated[$column] ?? null;
            }
        }

        if (Schema::hasColumn('users', 'siret') && $request->exists('siret')) {
            $nextSiret = $validated['siret'] ?? null;
            $data['siret'] = $nextSiret;

            if ($nextSiret !== $user->siret) {
                $data['siret_verified_at'] = null;
                $data['siret_verified_by'] = null;
            }
        }

        if (Schema::hasColumn('users', 'fcm_token') && $request->exists('fcm_token')) {
            $data['fcm_token'] = $validated['fcm_token'] ?? null;
        }

        foreach ([
            'coach_title',
            'coach_short_description',
            'coach_speciality',
            'coach_experience_years',
            'coach_certifications',
            'coach_languages',
        ] as $column) {
            if (Schema::hasColumn('users', $column) && $request->exists($column)) {
                $data[$column] = $validated[$column] ?? null;
            }
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->boolean('remove_photo') && Schema::hasColumn('users', 'photo')) {
            if ($user->photo) {
                $filesToDelete[] = $user->photo;
            }

            $data['photo'] = null;
        }

        if ($request->hasFile('photo') && Schema::hasColumn('users', 'photo')) {
            $newPhoto = $request->file('photo')->store('profiles', 'public');

            if ($user->photo && $user->photo !== $newPhoto) {
                $filesToDelete[] = $user->photo;
            }

            $data['photo'] = $newPhoto;
        }

        if ($request->boolean('remove_cover_photo') && Schema::hasColumn('users', 'cover_photo')) {
            if ($user->cover_photo) {
                $filesToDelete[] = $user->cover_photo;
            }

            $data['cover_photo'] = null;
        }

        if ($request->hasFile('cover_photo') && Schema::hasColumn('users', 'cover_photo')) {
            $newCoverPhoto = $request->file('cover_photo')->store('covers', 'public');

            if ($user->cover_photo && $user->cover_photo !== $newCoverPhoto) {
                $filesToDelete[] = $user->cover_photo;
            }

            $data['cover_photo'] = $newCoverPhoto;
        }

        if ($request->boolean('remove_presentation_video') && Schema::hasColumn('users', 'presentation_video')) {
            if ($user->presentation_video) {
                $filesToDelete[] = $user->presentation_video;
            }

            $data['presentation_video'] = null;

            if (Schema::hasColumn('users', 'presentation_video_duration_seconds')) {
                $data['presentation_video_duration_seconds'] = null;
            }
        }

        if ($request->hasFile('presentation_video') && Schema::hasColumn('users', 'presentation_video')) {
            $newPresentationVideo = $request->file('presentation_video')->store('coach-videos', 'public');

            if ($user->presentation_video && $user->presentation_video !== $newPresentationVideo) {
                $filesToDelete[] = $user->presentation_video;
            }

            $data['presentation_video'] = $newPresentationVideo;

            if (Schema::hasColumn('users', 'presentation_video_duration_seconds')) {
                $data['presentation_video_duration_seconds'] = (int) ceil($presentationVideoDuration);
            }
        }

        $user->update($data);

        foreach (array_unique(array_filter($filesToDelete)) as $fileToDelete) {
            if (Storage::disk('public')->exists($fileToDelete)) {
                Storage::disk('public')->delete($fileToDelete);
            }
        }

        $user->refresh();
        $user->load('roles');

        return response()->json([
            'status' => 200,
            'message' => 'Profil mis à jour avec succès',
            'user' => $this->formatUserProfile($user),
        ]);
    }

    public function publicIntervenants()
    {
        $roleHasName = Schema::hasColumn('roles', 'name');
        $roleHasSlug = Schema::hasColumn('roles', 'slug');

        $query = User::query()
            ->with('roles')
            ->whereHas('roles', function ($roleQuery) use ($roleHasName, $roleHasSlug) {
                $roleQuery->where(function ($q) use ($roleHasName, $roleHasSlug) {
                    if ($roleHasName) {
                        $q->where('name', 'Intervenant')
                            ->orWhere('name', 'intervenant')
                            ->orWhere('name', 'INTERVENANT');
                    }

                    if ($roleHasSlug) {
                        $q->orWhere('slug', 'intervenant')
                            ->orWhere('slug', 'Intervenant')
                            ->orWhere('slug', 'INTERVENANT');
                    }
                });
            });

        if (Schema::hasColumn('users', 'account_status')) {
            $query->where(function ($statusQuery) {
                $statusQuery
                    ->where('account_status', 'approved')
                    ->orWhere('account_status', 'active')
                    ->orWhereNull('account_status');
            });
        }

        $intervenants = $query
            ->latest()
            ->get()
            ->map(function ($user) {
                return $this->formatPublicIntervenant($user);
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Intervenants récupérés avec succès',
            'data' => $intervenants,
        ]);
    }

    private function formatUserProfile(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,

            'bio' => $this->getUserValue($user, 'bio'),
            'phone' => $this->getUserValue($user, 'phone'),
            'address' => $this->getUserValue($user, 'address'),
            'siret' => $this->getUserValue($user, 'siret'),
            'siret_verified_at' => $this->getUserValue($user, 'siret_verified_at'),
            'siret_verified' => $this->getUserValue($user, 'siret_verified_at') !== null,

            'account_status' => $this->getUserValue($user, 'account_status'),
            'roles' => $user->roles,

            'photo' => $this->getUserValue($user, 'photo'),
            'photo_url' => $this->getPhotoUrl($user),

            'cover_photo' => $this->getUserValue($user, 'cover_photo'),
            'cover_photo_url' => $this->getCoverPhotoUrl($user),

            'presentation_video' => $this->getUserValue($user, 'presentation_video'),
            'presentation_video_url' => $this->getPresentationVideoUrl($user),
            'presentation_video_duration_seconds' => $this->getUserValue($user, 'presentation_video_duration_seconds'),

            'coach_title' => $this->getUserValue($user, 'coach_title'),
            'coach_short_description' => $this->getUserValue($user, 'coach_short_description'),
            'coach_speciality' => $this->getUserValue($user, 'coach_speciality'),
            'coach_experience_years' => $this->getUserValue($user, 'coach_experience_years'),
            'coach_certifications' => $this->getUserValue($user, 'coach_certifications'),
            'coach_languages' => $this->getUserValue($user, 'coach_languages'),
            'professional_documents' => [
                'total' => $user->documents()->count(),
                'validated' => $user->documents()->where('status', 'valide')->count(),
                'requires_completion' => $user->hasRole('intervenant')
                    && (
                        ! $this->getUserValue($user, 'siret')
                        || ! $user->documents()
                            ->whereIn('document_type', ['diploma', 'certification'])
                            ->exists()
                    ),
            ],

            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function formatPublicIntervenant(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,

            'phone' => $this->getUserValue($user, 'phone'),
            'address' => $this->getUserValue($user, 'address'),
            'city' => $this->getUserValue($user, 'city'),
            'location' => $this->getUserValue($user, 'location'),

            'bio' => $this->getUserValue($user, 'bio'),

            'photo' => $this->getUserValue($user, 'photo'),
            'photo_url' => $this->getPhotoUrl($user),

            'cover_photo' => $this->getUserValue($user, 'cover_photo'),
            'cover_photo_url' => $this->getCoverPhotoUrl($user),

            'presentation_video' => $this->getUserValue($user, 'presentation_video'),
            'presentation_video_url' => $this->getPresentationVideoUrl($user),
            'presentation_video_duration_seconds' => $this->getUserValue($user, 'presentation_video_duration_seconds'),

            'coach_title' => $this->getUserValue($user, 'coach_title'),
            'coach_short_description' => $this->getUserValue($user, 'coach_short_description'),
            'coach_speciality' => $this->getUserValue($user, 'coach_speciality'),
            'coach_experience_years' => $this->getUserValue($user, 'coach_experience_years'),
            'coach_certifications' => $this->getUserValue($user, 'coach_certifications'),
            'coach_languages' => $this->getUserValue($user, 'coach_languages'),

            'account_status' => $this->getUserValue($user, 'account_status'),

            'speciality' => $this->getFirstAvailableUserValue($user, [
                'coach_speciality',
                'speciality',
                'specialty',
                'service',
            ], 'Bien-être'),

            'specialty' => $this->getFirstAvailableUserValue($user, [
                'coach_speciality',
                'specialty',
                'speciality',
                'service',
            ], 'Bien-être'),

            'service' => $this->getFirstAvailableUserValue($user, [
                'coach_speciality',
                'service',
                'speciality',
                'specialty',
            ], 'Bien-être'),

            'rating' => $this->getUserValue($user, 'rating', 4.8),
            'reviews_count' => $this->getUserValue($user, 'reviews_count', 0),

            'roles' => $user->roles,

            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function getUserValue(User $user, string $column, $default = null)
    {
        if (! Schema::hasColumn('users', $column)) {
            return $default;
        }

        return $user->{$column} ?? $default;
    }

    private function getFirstAvailableUserValue(User $user, array $columns, $default = null)
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn('users', $column)) {
                continue;
            }

            if (! empty($user->{$column})) {
                return $user->{$column};
            }
        }

        return $default;
    }

    private function getPhotoUrl(User $user)
    {
        if (Schema::hasColumn('users', 'photo_url') && ! empty($user->photo_url)) {
            return $user->photo_url;
        }

        if (Schema::hasColumn('users', 'photo') && ! empty($user->photo)) {
            return asset('storage/'.$user->photo);
        }

        return null;
    }

    private function getCoverPhotoUrl(User $user)
    {
        if (Schema::hasColumn('users', 'cover_photo_url') && ! empty($user->cover_photo_url)) {
            return $user->cover_photo_url;
        }

        if (Schema::hasColumn('users', 'cover_photo') && ! empty($user->cover_photo)) {
            return asset('storage/'.$user->cover_photo);
        }

        return null;
    }

    private function getPresentationVideoUrl(User $user)
    {
        if (Schema::hasColumn('users', 'presentation_video_url') && ! empty($user->presentation_video_url)) {
            return $user->presentation_video_url;
        }

        if (Schema::hasColumn('users', 'presentation_video') && ! empty($user->presentation_video)) {
            return asset('storage/'.$user->presentation_video);
        }

        return null;
    }

    private function deleteStoredUserFile(User $user, string $column): void
    {
        if (
            Schema::hasColumn('users', $column) &&
            ! empty($user->{$column}) &&
            Storage::disk('public')->exists($user->{$column})
        ) {
            Storage::disk('public')->delete($user->{$column});
        }
    }

    private function getVideoDurationInSeconds(string $path): ?float
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $ffprobe = trim((string) shell_exec('command -v ffprobe 2>/dev/null'));

        if ($ffprobe === '') {
            return null;
        }

        $command = escapeshellcmd($ffprobe)
            .' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
            .escapeshellarg($path)
            .' 2>/dev/null';

        $output = trim((string) shell_exec($command));

        if ($output === '' || ! is_numeric($output)) {
            return null;
        }

        return (float) $output;
    }
}
