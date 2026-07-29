<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    private const PROFESSIONAL_TYPES = [
        'diploma',
        'certification',
        'professional_card',
        'identity',
        'other',
    ];

    public function index(Request $request): JsonResponse
    {
        $documents = Document::with(['user:id,name,email,siret', 'validator:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('document_type'), fn ($query) => $query->where('document_type', (string) $request->string('document_type')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'documents' => $documents]);
    }

    public function myDocuments(Request $request): JsonResponse
    {
        $documents = Document::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'documents' => $documents]);
    }

    public function myCredentials(Request $request): JsonResponse
    {
        $documents = Document::where('user_id', $request->user()->id)
            ->whereIn('document_type', self::PROFESSIONAL_TYPES)
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'required_types' => ['diploma', 'certification'],
            'accepted_types' => self::PROFESSIONAL_TYPES,
            'credentials' => $documents,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->persist($request, false);
    }

    public function storeCredential(Request $request): JsonResponse
    {
        return $this->persist($request, true);
    }

    public function validerDocument($id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $document->update([
            'status' => 'valide',
            'validated_by' => Auth::id(),
            'validated_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Document validé avec succès',
            'document' => $document->fresh(['user:id,name,email,siret', 'validator:id,name']),
        ]);
    }

    public function refuserDocument(Request $request, $id): JsonResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $document = Document::findOrFail($id);
        $document->update([
            'status' => 'refuse',
            'validated_by' => Auth::id(),
            'validated_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Document refusé avec succès',
            'document' => $document->fresh(['user:id,name,email,siret', 'validator:id,name']),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $user = $request->user();

        if (! $user->hasRole('admin') && (int) $document->user_id !== (int) $user->id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Document supprimé avec succès',
        ]);
    }

    private function persist(Request $request, bool $professional): JsonResponse
    {
        $documentTypeRules = ['nullable', 'string', 'max:100'];

        if ($professional) {
            $documentTypeRules = ['required', Rule::in(self::PROFESSIONAL_TYPES)];
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_type' => $documentTypeRules,
            'document_number' => ['nullable', 'string', 'max:100'],
            'issuing_organization' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,png,jpg,jpeg,webp,heic,heif',
                'max:10240',
            ],
        ]);

        $filePath = $request->file('file')->store('documents', 'public');

        $document = Document::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'document_type' => $data['document_type'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'issuing_organization' => $data['issuing_organization'] ?? null,
            'issued_at' => $data['issued_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'file_path' => $filePath,
            'status' => 'en_attente',
        ]);

        return response()->json([
            'status' => 201,
            'message' => $professional
                ? 'Justificatif professionnel envoyé. Il attend la validation de l’administrateur.'
                : 'Document envoyé avec succès. Il attend la validation de l’administrateur.',
            'document' => $document,
        ], 201);
    }
}
