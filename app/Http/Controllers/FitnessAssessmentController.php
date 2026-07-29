<?php

namespace App\Http\Controllers;

use App\Models\FitnessAssessment;
use App\Models\FitnessAssessmentForm;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FitnessAssessmentController extends Controller
{
    public function activeForm(): JsonResponse
    {
        $form = FitnessAssessmentForm::active()
            ->orderByDesc('version')
            ->orderByDesc('published_at')
            ->first();

        if (! $form) {
            return response()->json([
                'status' => 404,
                'message' => 'Le formulaire de bilan de forme sera publié prochainement.',
                'form' => null,
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'form' => $form,
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('client')) {
            return response()->json([
                'status' => 403,
                'message' => 'Accès réservé aux coachés.',
            ], 403);
        }

        $assessment = FitnessAssessment::with('form')
            ->where('client_id', $request->user()->id)
            ->latest()
            ->first();

        return response()->json([
            'status' => 200,
            'assessment' => $assessment,
        ]);
    }

    public function saveMine(Request $request): JsonResponse
    {
        $client = $request->user();

        if (! $client->hasRole('client')) {
            return response()->json([
                'status' => 403,
                'message' => 'Accès réservé aux coachés.',
            ], 403);
        }

        $data = $request->validate([
            'form_id' => ['nullable', 'integer', 'exists:fitness_assessment_forms,id'],
            'answers' => ['required', 'array'],
            'status' => ['nullable', Rule::in(['draft', 'submitted'])],
        ]);

        $form = isset($data['form_id'])
            ? FitnessAssessmentForm::findOrFail($data['form_id'])
            : FitnessAssessmentForm::active()->orderByDesc('version')->first();

        if (! $form) {
            return response()->json([
                'status' => 422,
                'message' => 'Aucun formulaire de bilan de forme actif.',
            ], 422);
        }

        $status = $data['status'] ?? 'draft';

        if ($status === 'submitted') {
            $this->validateRequiredAnswers($form, $data['answers']);
        }

        $assessment = FitnessAssessment::updateOrCreate(
            [
                'client_id' => $client->id,
                'form_id' => $form->id,
            ],
            [
                'answers' => $data['answers'],
                'status' => $status,
                'submitted_at' => $status === 'submitted' ? now() : null,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'coach_notes' => null,
            ]
        );

        return response()->json([
            'status' => 200,
            'message' => $status === 'submitted'
                ? 'Bilan de forme transmis avec succès.'
                : 'Brouillon du bilan de forme enregistré.',
            'assessment' => $assessment->load('form'),
        ]);
    }

    public function byClient(Request $request, $clientId): JsonResponse
    {
        $client = User::findOrFail($clientId);

        if (! $this->canAccessClient($request->user(), $client)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $assessments = FitnessAssessment::with(['form', 'reviewer:id,name,email'])
            ->where('client_id', $client->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'client' => $client->only(['id', 'name', 'email']),
            'assessments' => $assessments,
        ]);
    }

    public function review(Request $request, $assessmentId): JsonResponse
    {
        $assessment = FitnessAssessment::with('client')->findOrFail($assessmentId);

        if (! $this->canAccessClient($request->user(), $assessment->client)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        if ($request->user()->hasRole('client')) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $data = $request->validate([
            'coach_notes' => ['required', 'string', 'max:5000'],
        ]);

        $assessment->update([
            'coach_notes' => $data['coach_notes'],
            'status' => 'reviewed',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Bilan de forme analysé avec succès.',
            'assessment' => $assessment->fresh(['form', 'reviewer:id,name,email']),
        ]);
    }

    public function forms(): JsonResponse
    {
        return response()->json([
            'status' => 200,
            'forms' => FitnessAssessmentForm::withCount('assessments')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function storeForm(Request $request): JsonResponse
    {
        $data = $this->validateForm($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['version'] = $data['version'] ?? 1;
        $data['created_by'] = $request->user()->id;

        $form = DB::transaction(function () use ($data) {
            if ($data['is_active'] ?? false) {
                FitnessAssessmentForm::query()->update(['is_active' => false]);
                $data['published_at'] = $data['published_at'] ?? now();
            }

            return FitnessAssessmentForm::create($data);
        });

        return response()->json([
            'status' => 201,
            'message' => 'Formulaire de bilan de forme créé.',
            'form' => $form,
        ], 201);
    }

    public function updateForm(Request $request, $formId): JsonResponse
    {
        $form = FitnessAssessmentForm::findOrFail($formId);
        $data = $this->validateForm($request, true, $form);

        DB::transaction(function () use ($form, $data) {
            if ($data['is_active'] ?? false) {
                FitnessAssessmentForm::whereKeyNot($form->id)->update(['is_active' => false]);
                $data['published_at'] = $data['published_at'] ?? $form->published_at ?? now();
            }

            $form->update($data);
        });

        return response()->json([
            'status' => 200,
            'message' => 'Formulaire de bilan de forme mis à jour.',
            'form' => $form->fresh(),
        ]);
    }

    private function validateForm(
        Request $request,
        bool $updating = false,
        ?FitnessAssessmentForm $form = null
    ): array {
        $presence = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('fitness_assessment_forms', 'slug')
                    ->where(fn ($query) => $query->where('version', $request->input('version', $form?->version ?? 1)))
                    ->ignore($form?->id),
            ],
            'title' => [$presence, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'version' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'questions' => [$presence, 'array'],
            'questions.*.key' => ['required', 'string', 'max:100', 'distinct'],
            'questions.*.label' => ['required', 'string', 'max:500'],
            'questions.*.type' => [
                'required',
                Rule::in([
                    'text',
                    'textarea',
                    'number',
                    'date',
                    'single_choice',
                    'multiple_choice',
                    'boolean',
                ]),
            ],
            'questions.*.required' => ['sometimes', 'boolean'],
            'questions.*.options' => ['sometimes', 'array'],
            'questions.*.options.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ]);
    }

    private function validateRequiredAnswers(FitnessAssessmentForm $form, array $answers): void
    {
        $errors = [];

        foreach ($form->questions ?? [] as $question) {
            if (! ($question['required'] ?? false)) {
                continue;
            }

            $key = $question['key'] ?? null;
            $value = $key !== null && array_key_exists($key, $answers)
                ? $answers[$key]
                : null;

            if ($key === null || $value === null || $value === '' || $value === []) {
                $errors["answers.{$key}"] = [
                    'La réponse à « '.($question['label'] ?? $key).' » est obligatoire.',
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function canAccessClient(User $user, User $client): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('client') && (int) $user->id === (int) $client->id) {
            return true;
        }

        return $user->hasRole('intervenant')
            && Reservation::where('client_id', $client->id)
                ->where('intervenant_id', $user->id)
                ->exists();
    }
}
