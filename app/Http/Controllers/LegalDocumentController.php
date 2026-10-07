<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LegalDocumentController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => 200,
            'documents' => LegalDocument::query()
                ->where('is_published', true)
                ->orderBy('audience')
                ->get(),
        ]);
    }

    public function show(string $slug)
    {
        $document = LegalDocument::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return response()->json(['status' => 200, 'document' => $document]);
    }

    public function adminIndex()
    {
        return response()->json([
            'status' => 200,
            'documents' => LegalDocument::query()->orderBy('audience')->get(),
        ]);
    }

    public function update(Request $request, LegalDocument $legalDocument)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:100'],
            'version' => ['required', 'string', 'max:50'],
            'effective_at' => ['nullable', 'date'],
            'is_published' => ['required', 'boolean'],
            'audience' => ['required', Rule::in(['client', 'intervenant'])],
        ]);

        $legalDocument->update($data);

        return response()->json([
            'status' => 200,
            'message' => 'Les CGV ont été mises à jour.',
            'document' => $legalDocument->fresh(),
        ]);
    }
}
